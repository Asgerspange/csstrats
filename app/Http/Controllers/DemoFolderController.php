<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{
    DemoFolder,
    Demo,
    TeamDemoFolder
};
use Illuminate\Http\{
    Request,
    JsonResponse
};
use Illuminate\Support\Facades\Auth;

class DemoFolderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $teams = $user->teams;
        $teamIds = $teams->pluck('id')->toArray();

        $folders = DemoFolder::with('sharedTeams')
            ->where('parent_id', null)
            ->where(function ($query) use ($user, $teamIds) {
                $query->where('user_id', $user->id)
                      ->orWhereHas('sharedTeams', function ($q) use ($teamIds) {
                          $q->whereIn('teams.id', $teamIds);
                      });
            })
            ->get();

        return new JsonResponse($folders, JsonResponse::HTTP_OK);
    }

    public function show(Request $request, DemoFolder $folder)
    {
        $user = $request->user();
        $teams = $user->teams;

        if ($folder->user_id !== $user->id && !$folder->sharedTeams()->whereIn('teams.id', $teams->pluck('id'))->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Load relationships; demos.rounds pulls the meta needed for lastRound
        $folder->load(['children', 'sharedTeams', 'demos.rounds:demo_id,meta']);

        // Loop through the demos already loaded into the folder model
        $folder->demos->each(function ($demo) {
            // Get the last round from the collection and pull the meta
            $lastRound = $demo->rounds->last();
            $demo->lastRound = $lastRound ? $lastRound->meta : null;
            
            // Remove the rounds collection so it doesn't bloat the JSON response
            $demo->makeHidden('rounds');
            unset($demo->meta);
        });

        return new JsonResponse($folder, JsonResponse::HTTP_OK);
    }

    public function store(Request $request)
    {
        $demoFolder = DemoFolder::create([
            'name' => 'New Folder',
            'user_id' => Auth::id(),
            'is_root' => true,
            'color' => dechex(rand(0x000000, 0xFFFFFF)),
        ]);

        TeamDemoFolder::create([
            'team_id' => $request->team_id ?? null,
            'demo_folder_id' => $demoFolder->id,
        ]);

        return response()->json($demoFolder, 201);
    }

    public function update(Request $request, DemoFolder $folder)
    {
        $request->validate([
            'folder_name' => 'sometimes|required|string|max:255',
            'color' => 'sometimes|nullable|string|max:12',
            'teams' => 'sometimes|array',
            'teams.*' => 'integer|exists:teams,id'
        ]);

        if ($request->has('folder_name')) {
            $folder->name = $request->folder_name;
        }
        if ($request->has('color')) {
            $folder->color = $request->color;
        }

        if ($request->has('teams')) {
            $teamIds = $request->teams;
            $this->syncFolderPermissionsRecursive($folder, $teamIds);
        }

        $folder->save();

        return response()->json($folder, 200);
    }

    /**
     * Move a demo into a folder and sync permissions.
     */
    public function moveDemoToFolder(Request $request, $id)
    {

    }

    /**
     * Private helper for recursive permission syncing.
     */
    private function syncFolderPermissionsRecursive(DemoFolder $folder, array $teamIds)
    {
        // 1. Sync the folder itself
        $folder->sharedTeams()->sync($teamIds);

        // 2. Sync all demos inside this folder
        $folder->demos()->each(function ($demo) use ($teamIds) {
            $demo->sharedTeams()->sync($teamIds);
        });

        // 3. Drill down into child folders
        foreach ($folder->children as $child) {
            $this->syncFolderPermissionsRecursive($child, $teamIds);
        }
    }

    /**
     * Delete folder and cleanup.
     */
    public function destroy(DemoFolder $folder)
    {
        return $this->detachAndDeleteChildren($folder);
    }

    private function detachAndDeleteChildren(DemoFolder $folder)
    {
        // Detach demos from this folder
        $folder->demos()->detach();
        
        // Remove folder team associations
        $folder->sharedTeams()->detach();

        foreach ($folder->children as $child) {
            $this->detachAndDeleteChildren($child);
            $child->delete();
        }

        $folder->delete();

        return response()->json(['message' => 'Demo folder deleted successfully.'], 200);
    }
}