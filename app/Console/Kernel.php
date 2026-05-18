<?php

namespace App\Console;

use App\Jobs\CheckMonitorJob;
use App\Models\Monitor;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * The scheduler runs every minute via cron (or `php artisan schedule:work` in dev).
     * Each monitor is dispatched only when its check_interval aligns with the current minute,
     * so a monitor with check_interval=5 fires at 0, 5, 10 ... minutes past the hour.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function () {
            $now = now();

            Monitor::all()->each(function (Monitor $monitor) use ($now) {
                // Fire only when the current minute is divisible by the monitor's interval
                if ($now->minute % $monitor->check_interval === 0) {
                    CheckMonitorJob::dispatch($monitor);
                }
            });
        })->everyMinute()->name('dispatch-monitor-checks')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
