<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Notifications\MonitorStatusChanged;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class CheckMonitorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Allow 3 attempts before giving up on a single check cycle.
     */
    public int $tries = 3;

    public function __construct(private Monitor $monitor)
    {
    }

    public function handle(): void
    {
        [$statusCode, $responseTimeMs, $isUp] = $this->performCheck();

        // Record this check
        MonitorCheck::create([
            'monitor_id' => $this->monitor->id,
            'status_code' => $statusCode,
            'response_time_ms' => $responseTimeMs,
            'is_up' => $isUp,
            'checked_at' => now(),
        ]);

        // Reload fresh from DB to avoid stale state across queue workers
        $this->monitor->refresh();
        $previousStatus = $this->monitor->status;
        $newStatus = $this->resolveStatus($isUp);

        $this->monitor->update([
            'status' => $newStatus,
            'last_checked_at' => now(),
        ]);

        // Only notify on a real status transition (not on "pending" → anything)
        if ($previousStatus !== 'pending' && $previousStatus !== $newStatus) {
            $this->sendNotification($newStatus);
        }
    }

    /**
     * Hit the URL and measure the response. Returns [statusCode, responseTimeMs, isUp].
     */
    private function performCheck(): array
    {
        $start = microtime(true);

        try {
            $response = Http::timeout(10)->get($this->monitor->url);
            $responseTimeMs = (int) round((microtime(true) - $start) * 1000);
            $statusCode = $response->status();
            // 2xx and 3xx are considered "up"
            $isUp = $statusCode >= 200 && $statusCode < 400;

            return [$statusCode, $responseTimeMs, $isUp];
        } catch (\Throwable $e) {
            // Timeout or connection refused: status_code = 0, response_time_ms = null
            Log::warning("Uptime check failed for {$this->monitor->url}: {$e->getMessage()}");

            return [0, null, false];
        }
    }

    /**
     * Determine new status using the threshold:
     * Only mark "down" after N consecutive failures.
     */
    private function resolveStatus(bool $isUp): string
    {
        if ($isUp) {
            return 'up';
        }

        // Pull the last {threshold} checks (most recent first)
        $recentChecks = $this->monitor
            ->checks()
            ->orderByDesc('checked_at')
            ->limit($this->monitor->threshold)
            ->pluck('is_up');

        $allFailed = $recentChecks->count() >= $this->monitor->threshold
            && $recentChecks->every(fn($up) => !$up);

        if ($allFailed) {
            return 'down';
        }

        // Not enough consecutive failures — preserve current status or stay pending
        return match ($this->monitor->status) {
            'up' => 'up',
            'down' => 'down',
            default => 'pending',
        };
    }

    /**
     * Send email notification via anonymous notifiable so no User model is required.
     */
    private function sendNotification(string $newStatus): void
    {
        $email = config('uptime.notify_email');

        if (!$email) {
            Log::warning('uptime.notify_email is not configured — skipping notification.');
            return;
        }

        Notification::route('mail', $email)
            ->notify(new MonitorStatusChanged($this->monitor, $newStatus));
    }
}
