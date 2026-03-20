<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\TrelloController;
use Illuminate\Console\Command;

class ChangeCustomFieldCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:change_custom_field';

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
        return $controller->changeCustomField();
    }
}
