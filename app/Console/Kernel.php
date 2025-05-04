<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

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
        'App\Console\Commands\PopulateAcademyCourseEnrollments',
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
        // Helper function to create a 'before' hook closure with the command name
        $createBeforeHook = function (string $commandName) {
            return function () use ($commandName) {
                // Use logger() or Log::info() etc.
                logger("Starting scheduled task: {$commandName}");
            };
        };

        // Helper function to create an 'after' hook closure with the command name
        $createAfterHook = function (string $commandName) {
            return function () use ($commandName) {
                logger("Finished scheduled task: {$commandName}");
            };
        };

        $commandName = 'stats:monthly';
        $schedule->command($commandName)
            ->monthlyOn(1, '00:00')
            ->onOneServer()
            ->before($createBeforeHook($commandName))
            ->after($createAfterHook($commandName));

        $commandName = 'moodle:sync';
        $schedule->command($commandName)
            ->everyThreeHours($minutes = 0)
            ->onOneServer()
            ->before($createBeforeHook($commandName))
            ->after($createAfterHook($commandName));

        $commandName = 'vatsim:flights';
        $schedule->command($commandName)
            ->everyMinute()
            ->onOneServer()
            ->before($createBeforeHook($commandName))
            ->after($createAfterHook($commandName));

        $commandName = 'moodle:sendexamemails';
        $schedule->command($commandName)
            ->everyFiveMinutes()
            ->onOneServer()
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
