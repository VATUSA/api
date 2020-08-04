<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOtsEvalsForeignKeys extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // ots_evals
        Schema::table('ots_evals', function (Blueprint $table) {
            $table->foreign('form_id')
                ->references('id')->on('ots_evals_forms')
                ->onDelete('cascade');
        });

        //ots_evals_perf_cats
        Schema::table('ots_evals_perf_cats', function (Blueprint $table) {
            $table->foreign('form_id')
                ->references('id')->on('ots_evals_forms')
                ->onDelete('cascade');
        });

        //ots_evals_perf_indicators
        Schema::table('ots_evals_perf_indicators', function (Blueprint $table) {
            $table->foreign('perf_cat_id')
                ->references('id')->on('ots_evals_perf_cats')
                ->onDelete('cascade');
        });

        //ots_evals_indicator_results
        Schema::table('ots_evals_indicator_results', function (Blueprint $table) {
            $table->foreign('perf_indicator_id')
                ->references('id')->on('ots_evals_perf_cats')
                ->onDelete('cascade');
            $table->foreign('eval_id')
                ->references('id')->on('ots_evals')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::disableForeignKeyConstraints();
    }
}
