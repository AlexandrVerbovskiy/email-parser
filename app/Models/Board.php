<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Board extends Model
{
    use HasFactory;

    protected $table = "boards";

    protected $fillable = [
        'board_id',
        'name'
    ];

    public function cards()
    {
        return $this->hasMany('App\Models\Card');
    }

    public function projects()
    {
        return $this->hasMany('App\Models\Project');
    }
}
