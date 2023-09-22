<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tg_subtasks', function (Blueprint $table) {
            $table->id();
            $table->string("chat_id")->nullable();
            $table->text('name')->nullable();
            $table->text('client')->nullable();
            $table->text('board')->nullable();
            $table->string('column')->nullable();
            $table->string('priority')->nullable();
            $table->string('part')->nullable();
            $table->string('typeestim')->nullable();
            $table->double('estim')->nullable();
            $table->text('project')->nullable();
            $table->text('milestone')->nullable();
            $table->text('release')->nullable();
            $table->date('start_date')->default(DB::raw('CURRENT_DATE'));
            $table->date('due_date')->default(DB::raw('CURRENT_DATE'));
            $table->text('desc')->nullable();
            $table->string('member')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tg_subtasks');
    }
};
