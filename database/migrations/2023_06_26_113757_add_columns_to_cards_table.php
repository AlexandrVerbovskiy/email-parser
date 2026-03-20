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
        Schema::table('cards', function (Blueprint $table) {
            $table->unsignedBigInteger("project_id")->nullable();
            $table->unsignedBigInteger("milestone_id")->nullable();
            $table->unsignedBigInteger("release_id")->nullable();
            $table->unsignedBigInteger("task_id")->nullable();
            $table->foreign('project_id')
                ->references('id')
                ->on('projects');
            $table->foreign('milestone_id')
                ->references('id')
                ->on('milestones');
            $table->foreign('release_id')
                ->references('id')
                ->on('releases');
            $table->foreign('task_id')
                ->references('id')
                ->on('tasks');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn("project_id");
            $table->dropColumn("milestone_id");
            $table->dropColumn("release_id");
            $table->dropColumn("task_id");
        });
    }
};
