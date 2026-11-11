<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backfill the legacy admin_demos pipeline into the unified demos table so
     * existing released tactics keep working after the admin pipeline is
     * removed. Each admin_demos row becomes a demos row (is_admin=1, owner=the
     * canonical admin account); its admin_demo_rounds become curated
     * demo_rounds (frame_data copied byte-for-byte); admin_demo_tactics are
     * repointed to the new demos ids.
     *
     * Idempotent: skips admin demos already backfilled (matched via
     * demos.legacy_admin_id). Does NOT drop legacy tables — that happens in a
     * later migration only after end-to-end verification.
     */
    public function up(): void
    {
        if (!Schema::hasTable('admin_demos') || !Schema::hasTable('admin_demo_rounds')) {
            return;
        }

        // frame_data rows are large gzipped blobs. Stream one at a time, don't
        // retain executed queries (their blob bindings blow up memory), and lift
        // the ceiling for this one-off data move.
        @ini_set('memory_limit', '-1');
        DB::connection()->disableQueryLog();

        $ownerId = (int) config('demos.admin_owner', 1);
        $hasTactics = Schema::hasTable('admin_demo_tactics');

        $adminDemoIds = DB::table('admin_demos')
            ->when(Schema::hasColumn('admin_demos', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->orderBy('id')
            ->pluck('id');

        foreach ($adminDemoIds as $adminDemoId) {
            $sourceRoundCount = DB::table('admin_demo_rounds')->where('demo_id', $adminDemoId)->count();

            // Idempotent + self-healing: skip only if a previous run fully copied
            // this demo. A partial row (e.g. from an interrupted run) is cleared
            // and redone. No tactic links point at the new id yet (the repoint
            // happens after this loop), so deleting a partial demo is safe.
            $existing = DB::table('demos')->where('legacy_admin_id', $adminDemoId)->first();
            if ($existing) {
                $copied = DB::table('demo_rounds')->where('demo_id', $existing->id)->count();
                if ($copied === $sourceRoundCount) {
                    continue;
                }
                DB::table('demo_rounds')->where('demo_id', $existing->id)->delete();
                DB::table('demos')->where('id', $existing->id)->delete();
            }

            $adminDemo = DB::table('admin_demos')->where('id', $adminDemoId)->first();

            DB::transaction(function () use ($adminDemo, $adminDemoId, $ownerId) {
                $newId = DB::table('demos')->insertGetId([
                    'name'            => $adminDemo->name,
                    'map'             => $adminDemo->map,
                    'owner'           => $ownerId,
                    'is_admin'        => 1,
                    'legacy_admin_id' => $adminDemo->id,
                    'hide'            => 0,
                    'version'         => 2,
                    'created_at'      => $adminDemo->created_at ?? now(),
                ]);

                // Copy rounds one blob at a time: list the indices (no blobs),
                // then fetch + insert each round individually so only a single
                // frame_data blob is ever held in memory.
                $roundIndices = DB::table('admin_demo_rounds')
                    ->where('demo_id', $adminDemoId)
                    ->orderBy('round_index')
                    ->pluck('round_index');

                foreach ($roundIndices as $roundIndex) {
                    $r = DB::table('admin_demo_rounds')
                        ->where('demo_id', $adminDemoId)
                        ->where('round_index', $roundIndex)
                        ->first();

                    DB::table('demo_rounds')->insert([
                        'demo_id'     => $newId,
                        'round_index' => $r->round_index,
                        'frame_data'  => $r->frame_data, // already gzipped + enriched
                        'meta'        => $r->meta ?? null,
                        'curated'     => 1,
                    ]);

                    unset($r);
                }
            });
        }

        // Repoint tactic links to the unified demos table.
        if ($hasTactics) {
            // The demo_id column still has a FK to admin_demos, so we cannot point
            // it at a demos.id until that constraint is dropped. It has to go
            // regardless — admin_demos is dropped in a later migration. Drop any
            // FK on admin_demo_tactics.demo_id that references admin_demos.
            $fks = DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'admin_demo_tactics'
                   AND COLUMN_NAME = 'demo_id'
                   AND REFERENCED_TABLE_NAME = 'admin_demos'"
            );
            foreach ($fks as $fk) {
                DB::statement("ALTER TABLE admin_demo_tactics DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            }

            // Collision-safe single statement: match each link's old admin id
            // against the scratch legacy_admin_id, write the new demos id.
            DB::statement(<<<'SQL'
                UPDATE admin_demo_tactics adt
                JOIN demos d ON d.legacy_admin_id = adt.demo_id AND d.is_admin = 1
                SET adt.demo_id = d.id
            SQL);
        }

        // Verification: every backfilled admin demo's round count must match,
        // and every tactic link must now resolve to an is_admin demo.
        $this->verify();
    }

    private function verify(): void
    {
        $mismatched = DB::table('demos')
            ->whereNotNull('legacy_admin_id')
            ->get(['id', 'legacy_admin_id'])
            ->filter(function ($d) {
                $new = DB::table('demo_rounds')->where('demo_id', $d->id)->count();
                $old = DB::table('admin_demo_rounds')->where('demo_id', $d->legacy_admin_id)->count();
                return $new !== $old;
            });

        if ($mismatched->isNotEmpty()) {
            throw new RuntimeException(
                'Admin demo backfill round-count mismatch for demos: '
                . $mismatched->pluck('id')->implode(', ')
            );
        }

        if (Schema::hasTable('admin_demo_tactics')) {
            $orphans = DB::table('admin_demo_tactics as adt')
                ->leftJoin('demos as d', function ($join) {
                    $join->on('d.id', '=', 'adt.demo_id')->where('d.is_admin', 1);
                })
                ->whereNull('d.id')
                ->when(Schema::hasColumn('admin_demo_tactics', 'deleted_at'),
                    fn ($q) => $q->whereNull('adt.deleted_at'))
                ->count();

            if ($orphans > 0) {
                throw new RuntimeException(
                    "Admin demo backfill left {$orphans} tactic link(s) not pointing at an is_admin demo."
                );
            }
        }
    }

    public function down(): void
    {
        // Remove only the rows this migration created (identified by the
        // scratch legacy_admin_id). Tactic-link repointing is not reversed.
        if (Schema::hasColumn('demos', 'legacy_admin_id')) {
            $ids = DB::table('demos')->whereNotNull('legacy_admin_id')->pluck('id');
            DB::table('demo_rounds')->whereIn('demo_id', $ids)->delete();
            DB::table('demos')->whereNotNull('legacy_admin_id')->delete();
        }
    }
};
