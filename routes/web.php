<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view("welcome");
});

Route::group(['prefix' => 'webhook'], function () {
    Route::controller(\App\Http\Controllers\WebHookController::class)->group(function () {
        Route::get('/tg', "tg");
        Route::get('/trello', "trello")->name("webhook_trello");
        Route::get('/test', "trello")->name("webhook_trello");
    });
});

Route::controller(\App\Http\Controllers\Api\TrelloController::class)->group(function () {
    Route::get('/test', "test")->name("test");
});

Route::group(['prefix' => 'admin'], function () {
    Route::controller(\App\Http\Controllers\AdminController::class)->group(function () {
        Route::get('/users', "users")->name("users");
        Route::get('/add/user', function (){return view("form", ["title" => "Add User", "action" => "addUser"]);})->name("form");
        Route::post('/add/user', "add")->name("addUser");
        Route::get('/edit/board/{id}', "formEdit")->name("form_edit");
        Route::post('/edit/user',  "edit")->name("edit");
        Route::get('/delete/{id}', "delete")->name("deleteUser");
        Route::get('/boards', "boards")->name("boards");
        Route::get('/add/board', function (){return view("addBoard");})->name("addBoard");
        Route::post('/add/board', "addBoard")->name("add_board");
        Route::get('/delete/board/{id}', "deleteBoard")->name("deleteBoard");
    });
});

Route::controller(\App\Http\Controllers\DashboardController::class)->group(function () {
    Route::get('/dashboard', "statisticsByProjects");
});
