<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent: an earlier run added referral_source_id before the FK
        // step failed on legacy zero-date rows, so guard each change.
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'referral_source_id')) {
                // Which referral source (if any) gets credit for this user.
                $table->unsignedBigInteger('referral_source_id')->nullable()->after('settings');
            }
            if (! Schema::hasColumn('users', 'referral_visitor_id')) {
                // Anonymous visitor id captured at attribution time (audit trail).
                $table->string('referral_visitor_id', 64)->nullable()->after('referral_source_id');
            }
            if (! Schema::hasColumn('users', 'referred_at')) {
                $table->timestamp('referred_at')->nullable()->after('referral_visitor_id');
            }
        });

        if (! $this->foreignKeyExists('users', 'users_referral_source_id_foreign')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('referral_source_id', 'users_referral_source_id_foreign')
                    ->references('id')->on('referral_sources')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if ($this->foreignKeyExists('users', 'users_referral_source_id_foreign')) {
                $table->dropForeign('users_referral_source_id_foreign');
            }
            foreach (['referral_source_id', 'referral_visitor_id', 'referred_at'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
