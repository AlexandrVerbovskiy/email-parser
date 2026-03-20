<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class trello_users extends Model
{
    use HasFactory;
    protected $fillable = [
        'trello_id',
        'name',
        'tag',
        "tg_username",
        'role',
        'cof'
    ];

    protected $table = 'trello_users';
}
