<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Milestone extends Model
{
    use HasFactory;

    protected $fillable = [
        "text",
        "project_id"
    ];

    public function project()
    {
        return $this->hasOne('App\Models\Project');
    }

    public function releases()
    {
        return $this->hasMany('App\Models\Release');
    }

    public function cards()
    {
        return $this->hasMany('App\Models\Card');
    }
}
