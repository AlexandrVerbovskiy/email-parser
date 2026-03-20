<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use HasFactory;

    protected $fillable = [
        "card_id",
        "custom_id",
        "name",
        "board_id",
        "estim",
        "estimIP",
        "extra",
        "estimQA",
        "member",
        "pm",
        "start_date",
        "end_date",
        "project_id",
        "milestone_id",
        "release_id",
        "task_id",
        "column",
        "link",
        "ready_date",
        "part",
        "pmd_link",
        "priority",
        "fact",
        "desc",
    ];

    public function board()
    {
        return $this->belongsTo('App\Models\Board');
    }

    public function project()
    {
        return $this->belongsTo('App\Models\Project');
    }

    public function milestone()
    {
        return $this->belongsTo('App\Models\Milestone');
    }

    public function release()
    {
        return $this->belongsTo('App\Models\Release');
    }

    public function task()
    {
        return $this->belongsTo('App\Models\Task');
    }

    public function dates()
    {
        return $this->hasMany('App\Models\CardDate');
    }
}
