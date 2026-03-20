<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TgSubtask extends Model
{
    use HasFactory;

    protected $fillable = [
        "chat_id",
        'name',
        'board',
        'column',
        'priority',
        'part',
        'type_estim',
        'estim',
        'project',
        'milestone',
        'release',
        'start_date',
        'due_date',
        'desc',
        'member',
    ];
}
