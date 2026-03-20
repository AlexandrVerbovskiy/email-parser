<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectStatistic extends Model
{
    use HasFactory;

    protected $fillable = [
        "project_id",
        "res",
        "date",
        "est_plan",
        "est_fact",
        "est_ready",
    ];
}
