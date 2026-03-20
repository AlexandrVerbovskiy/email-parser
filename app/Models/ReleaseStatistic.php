<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReleaseStatistic extends Model
{
    use HasFactory;

    protected $fillable = [
        "release_id",
        "res",
        "date",
        "est_plan",
        "est_fact",
        "est_ready",
    ];
}
