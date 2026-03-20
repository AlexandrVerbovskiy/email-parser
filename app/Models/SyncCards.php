<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncCards extends Model
{
    use HasFactory;

    protected $fillable = [
        "board_id",
        "date",
    ];
}
