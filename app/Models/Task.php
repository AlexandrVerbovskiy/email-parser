<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        "text",
        "release_id"
    ];

    public function release()
    {
        return $this->hasOne('App\Models\Release');
    }

    public function cards()
    {
        return $this->hasMany('App\Models\Card');
    }

    public function getTaskInfo(){
        $tasks = DB::table('boards as b')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('cards')
                    ->whereRaw('cards.board_id = b.id');
            })
            ->select([
                'b.id as board_id',
                'b.name as board_name',
                'p.id as project_id',
                'p.text as project_name',
                'm.id as milestone_id',
                'm.text as milestone_name',
                'r.id as release_id',
                'r.text as release_name',
                't.id as task_id',
                't.text as task_name',
                'c.id as card_id',
                'c.column as card_column',
                'c.member as card_member',
                'c.start_date as plan_start_date',
                'c.end_date as plan_end_date',
                'c.type as type',
                'c.pm as card_pm',
                'c.link as link',
                'u.role as role',
                'u.cof as cof',
                'cd.card_id as cd_card_id',
                'cd.qa as qa',
                DB::raw('MIN(cd.date) as fact_start_date'),
                DB::raw('MAX(cd.date) as fact_end_date'),
                DB::raw('SUM(cd.hours) as fact_est'),
                DB::raw(
                    'if(c.estim is null,
                     (if(c.estimQA is null ,
                     if(c.extra is null,
                     if(c.estimIP is null,
                     0,
                     c.estimIP),
                     c.extra) ,
                     c.estimQA)),
                     c.estim) as plan_est'
                )])
            ->join('cards as c', 'c.board_id', '=', 'b.id')
            ->leftJoin('trello_users as u', 'u.name', '=', 'c.member')
            ->join('projects as p', 'c.project_id', '=', 'p.id')
            ->join('milestones as m', 'c.milestone_id', '=', 'm.id')
            ->join('releases as r', 'c.release_id', '=', 'r.id')
            ->join('tasks as t', 'c.task_id', '=', 't.id')
            ->leftJoin('card_dates as cd', 'c.id', '=', 'cd.card_id')
            ->where("b.name", "!=", "Board for testing")
            ->where("c.column", "!=", "Just comments")
            ->groupBy(
                'b.id', 'b.name', 'p.id', 'p.text', 'm.id', 'm.text', 'r.id',
                'r.text', 't.id', 't.text', 'c.id', 'c.pm', 'c.member', 'c.start_date',
                'c.end_date', 'c.column', 'cd.card_id', "cd.qa", "c.type", "u.role", "c.estim", "c.estimQA",
                "c.link", "u.cof", "c.extra", "c.estimIP"
            );
        $tasks = $tasks->get();
        return $tasks;
    }

    public function getDevInfo($request = null)
    {
        $start = $request ? $request->input("start_date") : null;
        $end =  $request ? $request->input("end_date") : null;
        $result = Card::join('card_dates AS cd', 'cards.id', '=', 'cd.card_id')
            ->join('trello_users AS u', 'cards.member', '=', 'u.name')
            ->select(
                DB::raw('SUM(cd.hours) AS fact_est'),
                DB::raw('SUM(COALESCE(cards.estim, 0) + COALESCE(cards.estimQA, 0) + COALESCE(cards.estimIP, 0) + COALESCE(cards.extra, 0)) AS plan_est'),
                'cards.member AS member',
                'cards.part AS part',
                'u.front_cof as front_cof',
                'u.back_cof as back_cof',
                'u.role as role',
            )
            ->whereIn('cards.part', ['Backend', 'Frontend'])
            ->where('cards.column', '!=', 'Just comments')
            ->whereIn('cards.column', ['Ready for QA', 'Done'])
            ->when($start, function ($query, $start) {
                return $query->where('cards.start_date', ">=", $start);
            })
            ->when($end, function ($query, $end) {
                return $query->where('cards.end_date', "<=", $end);
            })
            ->groupBy('cards.member', 'cards.part', 'u.front_cof', 'u.back_cof', 'u.role')
            ->havingRaw('fact_est IS NOT NULL')
            ->havingRaw('plan_est != 0')
            ->get();
        return $result;
    }
}
