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
        Schema::create('all_clients_statistics', function (Blueprint $table) {
            $table->id();
            $table->double("res")->nullable();
            $table->date("date")->nullable();
            $table->double("est_fact")->nullable();
            $table->double("est_plan")->nullable();
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
        Schema::dropIfExists('all_clients_statistics');
    }
};
