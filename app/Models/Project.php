<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        "text",
        "board_id"
    ];

    public function board()
    {
        return $this->hasOne('App\Models\Board');
    }

    public function milestones()
    {
        return $this->hasMany('App\Models\Milestone');
    }

    public function cards()
    {
        return $this->hasMany('App\Models\Card');
    }
}
