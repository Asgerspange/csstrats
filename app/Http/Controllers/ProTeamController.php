<?php

namespace App\Http\Controllers;

use App\Services\CacheService;
use Illuminate\Http\JsonResponse;


class ProTeamController extends Controller
{
    public function __construct(
        private readonly CacheService $cacheService
    ) {}

    public function index()
    {
        $proTeams = $this->cacheService->getProTeams();

        $proTeams = $proTeams->map(function ($team) {
            return (object) ['id' => $team['id'], 'name' => $team['name']];
        });
        return new JsonResponse($proTeams, JsonResponse::HTTP_OK);
    }
}
