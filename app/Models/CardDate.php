<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardDate extends Model
{
    use HasFactory;

    protected $fillable = [
        "id",
        "date",
        "hours",
        "card_id",
        "qa",
    ];

    public function card()
    {
        return $this->belongsTo('App\Models\Card');
    }
}
