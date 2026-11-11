<?php

namespace App\Http\Controllers;

use App\Models\Map;
use App\Models\Nade;
use App\Models\ThrowPoint;
use Illuminate\Http\Request;

class NadeMapController extends Controller
{
    public function storeNade(Request $request, string $mapSlug)
    {
        $map = Map::where('slug', $mapSlug)->firstOrFail();

        $validated = $request->validate([
            'type' => 'required|in:smoke,molotov,flashbang,grenade',
            'side' => 'required|in:ct,t,both',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'landing_x' => 'required|numeric',
            'landing_y' => 'required|numeric',
        ]);

        Nade::create(array_merge($validated, ['map_id' => $map->id, 'active' => true]));

        return back();
    }

    public function updateNade(Request $request, Nade $nade)
    {
        $validated = $request->validate([
            'type' => 'required|in:smoke,molotov,flashbang,grenade',
            'side' => 'required|in:ct,t,both',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'landing_x' => 'required|numeric',
            'landing_y' => 'required|numeric',
            'active' => 'boolean',
        ]);

        $nade->update($validated);
        return back();
    }

    public function deleteNade(Nade $nade)
    {
        $nade->delete();
        return back();
    }

    public function storeThrowPoint(Request $request, Nade $nade)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'throw_x' => 'required|numeric',
            'throw_y' => 'required|numeric',
            'video_url' => 'required|url',
            'technique' => 'required|in:normal,jumpthrow,runthrow,walkthrow,crouchthrow,runjumpthrow,walkjumpthrow',
            'additional_info' => 'nullable|string',
        ]);

        ThrowPoint::create(array_merge($validated, ['nade_id' => $nade->id, 'active' => true, 'order' => 0]));
        return back();
    }

    public function updateThrowPoint(Request $request, ThrowPoint $throwPoint)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'throw_x' => 'required|numeric',
            'throw_y' => 'required|numeric',
            'video_url' => 'required|url',
            'technique' => 'required|in:normal,jumpthrow,runthrow,walkthrow,crouchthrow,runjumpthrow,walkjumpthrow',
            'additional_info' => 'nullable|string',
            'active' => 'boolean',
        ]);

        $throwPoint->update($validated);
        return back();
    }

    public function deleteThrowPoint(ThrowPoint $throwPoint)
    {
        $throwPoint->delete();
        return back();
    }
}