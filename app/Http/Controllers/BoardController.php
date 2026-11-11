<?php

namespace App\Http\Controllers;

use App\Models\{
    Board,
    BoardSnapshot,
    Team,
};
use App\Constants\AppErrorCode;
use App\Services\EntitlementService;
use Illuminate\Http\{
    Request,
    JsonResponse
};
use Illuminate\Support\Facades\{
    DB,
    Http
};

class BoardController extends Controller
{
    private $storageZoneName;
    private $storagePassword;
    private $cdnUrl;
    private $storageBaseUrl;

    public function __construct()
    {
        $this->storageZoneName = config('services.bunny.storage_zone');
        $this->storagePassword = config('services.bunny.storage_password');
        $this->cdnUrl = config('services.bunny.cdn_url');
        $this->storageBaseUrl = config('services.bunny.storage_url', 'https://storage.bunnycdn.com');
    
        if (app()->environment('local', 'development') || !env('VERIFY_SSL', true)) {
            Http::globalOptions(['verify' => false]);
        }
    }
    public function index(Request $request)
    {
        $query = Board::with('snapshot')->where('team_id', $request->team_id);

        // For map_based teams, only return boards on accessible maps
        $team = Team::find($request->team_id);
        if ($team && $team->entitlement_mode === 'map_based') {
            $entitlement = app(EntitlementService::class);
            $accessibleMapIds = $entitlement->accessibleMapIds($team);
            $query->whereIn('map_id', $accessibleMapIds);
        }

        return new JsonResponse([
            'boards' => $query->get()
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id'        => 'sometimes|nullable|integer',
            'tactic_id' => 'sometimes|nullable|integer',
            'team_id'   => 'required|integer',
            'name'      => 'required|string|max:255',
            'map_id'    => 'required|integer',
            'data'      => 'required|string',
            'file'      => 'required|file|image|mimes:png|max:10240'
        ]);

        // Map access gate for map_based teams
        $team = Team::find($validated['team_id']);
        if ($team && $team->entitlement_mode === 'map_based') {
            $entitlement = app(EntitlementService::class);
            if (! $entitlement->canEditMap($team, $validated['map_id'])) {
                return new JsonResponse([
                    'app_code' => AppErrorCode::NO_MAP_EDIT_ACCESS,
                    'message' => 'This map is not in your plan. Upgrade to edit.',
                ], 403);
            }
        }

        return DB::transaction(function () use ($validated, $request) {
            $decodedData = json_decode($validated['data'], true);

            if (isset($validated['tactic_id'])) {
                if (Board::where('tactic_id', $validated['tactic_id'])
                    ->where('team_id', $validated['team_id'])
                    ->where('id', '!=', $request->id ?? 0)
                    ->exists()) {
                    return new JsonResponse([
                        'message' => 'A board for this tactic already exists in the team.'
                    ], JsonResponse::HTTP_CONFLICT);
                }
            }

            $board = Board::updateOrCreate(
                ['id' => $request->id],
                [
                    'team_id'   => $validated['team_id'],
                    'tactic_id' => $validated['tactic_id'] ?? null,
                    'name'      => $validated['name'],
                    'map_id'    => $validated['map_id'],
                    'data'      => $decodedData,
                ]
            );

            BoardSnapshot::where('board_id', $board->id)->delete();

            $snapshotUrl = $this->uploadImageToBunny($request->file('file'), $board->id);

            BoardSnapshot::create([
                'board_id'  => $board->id,
                'tactic_id' => $board->tactic_id,
                'team_id'   => $board->team_id,
                'url'       => $snapshotUrl,
            ]);

            return new JsonResponse($board, JsonResponse::HTTP_CREATED);
        });
    }
    private function uploadImageToBunny($file, $boardId): string
    {
        if (!$file) {
            throw new \Exception("No file provided for upload.");
        }

        if ($boardId <= 0) {
            throw new \Exception("Invalid board ID for upload.");
        }

        $fileName = "board_snapshots/{$boardId}.png";
        $uploadUrl = "{$this->storageBaseUrl}/{$this->storageZoneName}/{$fileName}";

        $fileContents = is_string($file) ? $file : file_get_contents($file->getRealPath());

        $response = Http::withHeaders([
            'AccessKey'    => $this->storagePassword,
            'Content-Type' => 'image/png',
        ])->withBody($fileContents, 'image/png')->put($uploadUrl);

        if (!$response->successful()) {
            \Log::error("BunnyCDN Upload Failed: " . $response->body());
            throw new \Exception("Failed to upload snapshot to storage.");
        }

        return "{$this->cdnUrl}/{$fileName}?t=" . time();
    }
}
