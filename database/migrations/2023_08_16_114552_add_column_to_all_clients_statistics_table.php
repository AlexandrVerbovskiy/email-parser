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
        Schema::table('all_clients_statistics', function (Blueprint $table) {
            $table->double("est_ready")->nullable()->after("est_plan");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('all_clients_statistics', function (Blueprint $table) {
            $table->dropColumn("est_ready");
        });
    }
};
