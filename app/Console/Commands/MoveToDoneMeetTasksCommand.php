<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MoveToDoneMeetTasksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:move_to_done_meet';

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
        app()->make('App\Http\Controllers\Api\TrelloController')->moveToDoneMeet();
    }
}
