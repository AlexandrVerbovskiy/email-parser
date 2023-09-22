<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetDescCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:set_desc';

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
        app()->make('App\Http\Controllers\Api\TrelloController')->setDesc();
    }
}
