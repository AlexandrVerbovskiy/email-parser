<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CreateMeetTasksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:create_meet_tasks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        app()->make('App\Http\Controllers\Api\TrelloController')->createMeetTasks();
    }
}
