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
        Schema::table('trello_users', function (Blueprint $table) {
            $table->double("front_cof")->default(1.5);
            $table->double("back_cof")->default(1.5);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('trello_users', function (Blueprint $table) {
            $table->dropColumn("front_cof");
            $table->dropColumn("back_cof");
        });
    }
};
