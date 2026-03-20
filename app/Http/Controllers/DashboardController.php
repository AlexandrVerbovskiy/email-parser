<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Board;
use App\Models\Card;
use App\Models\CardDate;
use App\Models\tg_users;
use App\Models\trello_users;

class DashboardController extends Controller
{
    public function statisticsByProjects(Request $request)
    {
        if ($request->input("key"))
            $projects = Board::has("cards")->with("cards.dates")->where("name", "like",$request->input("key") . "%")->get();
        else
            $projects = Board::has("cards")->with("cards.dates")->get();
        foreach ($projects as $board) {
            $dateFactSum = 0;
            $datePlanSum = 0;
            foreach ($board->cards as $card) {
                $dateFactSumCard = 0;
                $datePlanSum += $card->estimation;
                foreach ($card->dates as $date) {
                    $dateFactSumCard += $date->hours;
                    $dateFactSum += $date->hours;
                }
                $card->date_fact_sum = $dateFactSumCard;
            }
            $board->date_fact_sum = $dateFactSum;
            $board->date_plan_sum = $datePlanSum;
        }
        return view("statistics.byProjects", ["projects" => $projects]);
    }
}
