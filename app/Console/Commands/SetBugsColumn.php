<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Api\TrelloController;


class SetBugsColumn extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:set_bugs_column';

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
    public function handle(TrelloController $controller)
    {
        $controller->removeCardsFromHotFixColumn();
        $controller->renameBugFixColumn();
        return true;
    }
}
