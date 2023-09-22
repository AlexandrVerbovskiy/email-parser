<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DevStatistic extends Model
{
    use HasFactory;

    protected $fillable = [
        "member",
        "plan_back",
        "plan_front",
        "fact_back",
        "fact_front",
        "date"
    ];
}
