<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Release extends Model
{
    use HasFactory;

    protected $fillable = [
        "text",
        "milestone_id"
    ];

    public function milestone()
    {
        return $this->hasOne('App\Models\Milestone');
    }

    public function tasks()
    {
        return $this->hasMany('App\Models\Task');
    }

    public function cards()
    {
        return $this->hasMany('App\Models\Card');
    }
}
