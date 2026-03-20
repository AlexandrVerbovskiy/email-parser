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
        Schema::create('project_statistics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("project_id")->nullable();
            $table->double("res")->nullable();
            $table->date("date")->nullable();
            $table->double("est_fact")->nullable();
            $table->double("est_plan")->nullable();
            $table->timestamps();


            $table->foreign('project_id')
                ->references('id')
                ->on('projects');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('project_statistics');
    }
};
