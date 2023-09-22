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
        Schema::create('milestone_statistics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("milestone_id")->nullable();
            $table->double("res")->nullable();
            $table->date("date")->nullable();
            $table->double("est_fact")->nullable();
            $table->double("est_plan")->nullable();
            $table->timestamps();


            $table->foreign('milestone_id')
                ->references('id')
                ->on('milestones');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('milestone_statistics');
    }
};
