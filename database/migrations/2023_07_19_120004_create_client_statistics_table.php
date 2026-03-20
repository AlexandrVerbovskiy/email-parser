<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::create('client_statistics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("client_id")->nullable();
            $table->double("res")->nullable();
            $table->double("est_fact")->nullable();
            $table->double("est_plan")->nullable();
            $table->date("date")->nullable();
            $table->timestamps();


            $table->foreign('client_id')
                ->references('id')
                ->on('boards');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('client_statistics');
    }
};
