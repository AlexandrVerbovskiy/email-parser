<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use HasFactory;

    protected $fillable = [
      "card_id",
      "custom_id",
      "name",
      "board_id",
      "estimation",
      "member"
    ];

    public function board()
    {
        return $this->belongsTo('App\Models\Board');
    }

    public function dates()
    {
        return $this->hasMany('App\Models\CardDate');
    }
}
