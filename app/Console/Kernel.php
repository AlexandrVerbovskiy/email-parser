<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('command:move_to_wait')->dailyAt('15:00');
        $schedule->command('command:change_due_date')->sundays()->at('20:00');
        $schedule->command('command:statistic')->everyFifteenMinutes();
        $schedule->command('command:dev_statistic')->everyFifteenMinutes();
        $schedule->command('command:notification')
            ->weekdays()
            ->everyThirtyMinutes()
            ->between('6:00', '14:30');
        $schedule->command('command:create_meet_tasks')
            ->weekdays()
            ->at('6:30');
        $schedule->command('command:move_to_done_meet')
            ->weekdays()
            ->at('7:00');
        $schedule->command('command:many_cards_in_progress')
            ->weekdays()
            ->everyMinute()
            ->between('6:00', '14:30');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    protected $commands = [
        Commands\SetStatisticCommand::class,
        Commands\CreateMeetTasksCommand::class,
        Commands\MoveToDoneMeetTasksCommand::class,
        Commands\SetDescCommand::class,
        Commands\ManyCardsInProgressCommand::class,
        Commands\NotificationCommand::class,
        Commands\changeDueDatesCommand::class,
        Commands\setDevStatisticCommand::class,
        Commands\ChangeCustomFieldCommand::class,
        Commands\SetEstimCommand::class,
        Commands\SetBugsColumn::class,
    ];
}
