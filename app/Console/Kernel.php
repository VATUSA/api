<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        'App\Console\Commands\MoodleSync',
        'App\Console\Commands\StatsMonthly',
        'App\Console\Commands\VATSIMFlights',
        'App\Console\Commands\SendAcademyRatingExamEmails',
        'App\Console\Commands\MoodleCompetency',
        'App\Console\Commands\CacheControllerEligibility',
    ];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Helper function to create a 'before' hook closure with the command name.
        // Records the start time in cache (not a shared closure variable) because
        // runInBackground() events run their 'after' hook in a separate, freshly-booted
        // `schedule:finish` process with no memory in common with this one.
        $createBeforeHook = function (string $commandName) {
            return function () use ($commandName) {
                logger("Starting scheduled task: {$commandName}");
                Cache::put("schedule:started:{$commandName}", now(), now()->addHours(6));
            };
        };

        // Helper function to create an 'after' hook closure with the command name.
        // $warnAfterMinutes, when given, logs a WARN if the run exceeded that duration —
        // used for commands with a withoutOverlapping() lock window to catch a run
        // approaching that window before it starts stacking overlapping instances.
        $createAfterHook = function (string $commandName, ?int $warnAfterMinutes = null) {
            return function () use ($commandName, $warnAfterMinutes) {
                $startedAt = Cache::pull("schedule:started:{$commandName}");
                $elapsedMinutes = $startedAt ? round(now()->diffInSeconds($startedAt) / 60, 1) : null;

                logger("Finished scheduled task: {$commandName}" .
                    ($elapsedMinutes !== null ? " ({$elapsedMinutes} min)" : ""));

                if ($warnAfterMinutes !== null && $elapsedMinutes !== null && $elapsedMinutes > $warnAfterMinutes) {
                    Log::warning("Scheduled task {$commandName} took {$elapsedMinutes} minutes, exceeding the {$warnAfterMinutes}-minute warn threshold.");
                }
            };
        };

        $commandName = 'stats:monthly';
        $schedule->command($commandName)
            ->monthlyOn(1, '00:00')
            ->onOneServer()
            ->runInBackground()
            ->before($createBeforeHook($commandName))
            ->after($createAfterHook($commandName));

        $commandName = 'moodle:sync';
        $schedule->command($commandName)
            ->everyThreeHours($minutes = 0)
            ->onOneServer()
            ->runInBackground()
            ->withoutOverlapping(120)
            ->before($createBeforeHook($commandName))
            ->after($createAfterHook($commandName, 90));

        $commandName = 'vatsim:flights';
        $schedule->command($commandName)
            ->everyMinute()
            ->onOneServer()
            ->withoutOverlapping()
            ->runInBackground()
            ->before($createBeforeHook($commandName))
            ->after($createAfterHook($commandName));

        $commandName = 'moodle:sendexamemails';
        $schedule->command($commandName)
            ->everyFiveMinutes()
            ->onOneServer()
            ->runInBackground()
            ->before($createBeforeHook($commandName))
            ->after($createAfterHook($commandName));

        $commandName = "moodle:competency";
        $schedule->command($commandName)
            ->everyTenMinutes()
            ->onOneServer()
            ->withoutOverlapping(60)
            ->runInBackground()
            ->before($createBeforeHook($commandName))
            ->after($createAfterHook($commandName, 45));

        $commandName = "controller:eligibility";
        $schedule->command($commandName)
            ->hourly()
            ->onOneServer()
            ->runInBackground()
            ->before($createBeforeHook($commandName))
            ->after($createAfterHook($commandName));

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
