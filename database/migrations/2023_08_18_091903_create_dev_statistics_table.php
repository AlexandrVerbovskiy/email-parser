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
        Schema::create('dev_statistics', function (Blueprint $table) {
            $table->id();
            $table->string("member");
            $table->double("plan_back")->nullable();
            $table->double("plan_front")->nullable();
            $table->double("fact_back")->nullable();
            $table->double("fact_front")->nullable();
            $table->date("date");
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
        Schema::dropIfExists('dev_statistics');
    }
};
