<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientStatistic extends Model
{
    use HasFactory;

    protected $fillable = [
        "client_id",
        "res",
        "date",
        "est_plan",
        "est_fact",
        "est_ready",
    ];
}
