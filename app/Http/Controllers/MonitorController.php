<?php

namespace App\Http\Controllers;

use App\Jobs\CheckMonitorJob;
use App\Models\Monitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MonitorController
{
    /**
     * POST /api/monitors
     * Register a new URL to monitor.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => [
                'required',
                'url',
                Rule::unique('monitors', 'url'),
            ],
            'check_interval' => 'sometimes|integer|min:1|max:60',
            'threshold'      => 'sometimes|integer|min:1',
        ]);

        $monitor = Monitor::create([
            'url'            => $data['url'],
            'check_interval' => $data['check_interval'] ?? 5,
            'threshold'      => $data['threshold'] ?? 3,
            'status'         => 'pending',
        ]);

        // Dispatch the first check immediately (async via queue)
        CheckMonitorJob::dispatch($monitor);

        return response()->json(
            ['data' => $this->formatMonitor($monitor)],
            201
        );
    }

    /**
     * GET /api/monitors
     * List all monitors with their current status.
     */
    public function index(): JsonResponse
    {
        $monitors = Monitor::all()->map(fn(Monitor $m) => $this->formatMonitor($m));

        return response()->json(['data' => $monitors]);
    }

    /**
     * GET /api/monitors/{id}/history
     * Fetch paginated check history for a specific monitor.
     */
    public function history(Request $request, int $id): JsonResponse
    {
        $monitor = Monitor::find($id);

        if (! $monitor) {
            return response()->json(['message' => 'Monitor not found.'], 404);
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $page    = max((int) $request->query('page', 1), 1);

        $checks = $monitor->checks()
            ->orderByDesc('checked_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $checks->map(fn($c) => [
                'id'               => $c->id,
                'monitor_id'       => $c->monitor_id,
                'status_code'      => $c->status_code,
                'response_time_ms' => $c->response_time_ms,
                'is_up'            => $c->is_up,
                'checked_at'       => $c->checked_at->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $checks->currentPage(),
                'per_page'     => $checks->perPage(),
                'total'        => $checks->total(),
            ],
        ]);
    }

    /**
     * Shared monitor formatter matching the API contract shape.
     */
    private function formatMonitor(Monitor $monitor): array
    {
        return [
            'id'                => $monitor->id,
            'url'               => $monitor->url,
            'check_interval'    => $monitor->check_interval,
            'threshold'         => $monitor->threshold,
            'status'            => $monitor->status,
            'last_checked_at'   => $monitor->last_checked_at?->toIso8601String(),
            'uptime_percentage' => $monitor->uptime_percentage,
            'created_at'        => $monitor->created_at->toIso8601String(),
        ];
    }
}
