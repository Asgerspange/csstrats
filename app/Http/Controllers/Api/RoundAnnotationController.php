<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoundAnnotation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoundAnnotationController extends Controller
{
    /**
     * GET /api/demos/{demo}/rounds/{round}/annotations
     * Returns all annotations for a given demo + round, ordered by creation time.
     */
    public function index(int $demoId, int $roundNumber)
    {
        $annotations = RoundAnnotation::with('user:id,name')
            ->forRound($demoId, $roundNumber)
            ->orderBy('created_at')
            ->get();

        return response()->json($annotations);
    }

    /**
     * POST /api/demos/{demo}/rounds/{round}/annotations
     */
    public function store(Request $request, int $demoId, int $roundNumber)
    {
        $validated = $request->validate([
            'type'       => 'required|in:note,area,freehand,utility',
            'content'    => 'nullable|string|max:2000',
            'shape_data' => 'nullable|array',
            'color'      => 'nullable|string|max:9',
            'label'      => 'nullable|string|max:80',
            'tick'       => 'nullable|integer|min:0',
        ]);

        $annotation = RoundAnnotation::create([
            ...$validated,
            'demo_id'      => $demoId,
            'round_number' => $roundNumber,
            'user_id'      => Auth::id(),
            'color'        => $validated['color'] ?? '#5D3FD3',
        ]);

        return response()->json($annotation->load('user:id,name'), 201);
    }

    /**
     * PATCH /api/annotations/{annotation}
     */
    public function update(Request $request, RoundAnnotation $annotation)
    {
        $this->authorize('update', $annotation);

        $validated = $request->validate([
            'content'    => 'nullable|string|max:2000',
            'shape_data' => 'nullable|array',
            'color'      => 'nullable|string|max:9',
            'label'      => 'nullable|string|max:80',
            'tick'       => 'nullable|integer|min:0',
        ]);

        $annotation->update($validated);

        return response()->json($annotation);
    }

    /**
     * DELETE /api/annotations/{annotation}
     */
    public function destroy(RoundAnnotation $annotation)
    {
        $this->authorize('delete', $annotation);
        $annotation->delete();

        return response()->json(['ok' => true]);
    }
}