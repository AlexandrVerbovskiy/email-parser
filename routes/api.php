<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/tg', [\App\Http\Controllers\Api\TgController::class, "sendMessage"]);
Route::get('/trello', [\App\Http\Controllers\Api\TrelloController::class, "check"]);
Route::post('/trello', [\App\Http\Controllers\Api\TrelloController::class, "sendMessage"]);


