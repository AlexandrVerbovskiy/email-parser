<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AllClientsStatistic extends Model
{
    use HasFactory;

    protected $fillable = [
        "res",
        "date",
        "est_plan",
        "est_fact",
        "est_ready",
    ];
}
