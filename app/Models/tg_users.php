<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tg_users extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'name',
        "chat_id"
    ];

    protected $table = 'tg_users';
}
