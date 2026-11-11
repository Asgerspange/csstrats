<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class AdminCommandsController extends Controller
{
    /**
     * 'execute_option' is the flag to pass for real execution.
     * null = run the command without any flag.
     * 'execute' = pass --execute (stripe:resync requires this).
     */
    private array $allowedCommands = [
        'stripe:resync' => [
            'description' => 'Resync Stripe subscriptions and soft-delete content for cancelled subs',
            'supports_dry_run' => true,
            'execute_option' => 'execute',
        ],
        'maps:provision' => [
            'description' => 'Provision missing map stratbooks for map_based teams',
            'supports_dry_run' => false,
            'execute_option' => null,
        ],
        'maps:check-pool-rotation' => [
            'description' => 'Detect Active Duty pool changes, void locks, provision new maps',
            'supports_dry_run' => true,
            'execute_option' => null,
        ],
        'subs:handle-lapsed' => [
            'description' => 'Soft-delete content for past-due subscriptions older than 7 days',
            'supports_dry_run' => true,
            'execute_option' => null,
        ],
    ];

    /**
     * GET /api/admin/commands — list available commands.
     */
    public function index(): JsonResponse
    {
        $commands = [];
        foreach ($this->allowedCommands as $name => $config) {
            $commands[] = [
                'name' => $name,
                'description' => $config['description'],
                'supports_dry_run' => $config['supports_dry_run'],
                'execute_option' => $config['execute_option'],
            ];
        }

        return new JsonResponse(['commands' => $commands]);
    }

    /**
     * POST /api/admin/commands/run — run a command and return its output.
     */
    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command' => 'required|string',
            'mode' => 'nullable|string|in:dry-run,execute',
        ]);

        $command = $validated['command'];
        $mode = $validated['mode'] ?? 'execute';

        if (! isset($this->allowedCommands[$command])) {
            return new JsonResponse(['message' => 'Unknown command.'], 422);
        }

        $config = $this->allowedCommands[$command];

        if ($mode === 'dry-run' && ! $config['supports_dry_run']) {
            return new JsonResponse(['message' => 'This command does not support dry-run.'], 422);
        }

        $fullCommand = $command;
        if ($mode === 'dry-run') {
            $fullCommand .= ' --dry-run';
        } elseif ($config['execute_option'] !== null) {
            $fullCommand .= " --{$config['execute_option']}";
        }

        Log::info('Admin command triggered', [
            'command' => $fullCommand,
            'user_id' => $request->user()->id,
        ]);

        $exitCode = Artisan::call($fullCommand);
        $output = Artisan::output();

        return new JsonResponse([
            'command' => $fullCommand,
            'exit_code' => $exitCode,
            'output' => $output,
            'success' => $exitCode === 0,
        ], JsonResponse::HTTP_OK);
    }
}
