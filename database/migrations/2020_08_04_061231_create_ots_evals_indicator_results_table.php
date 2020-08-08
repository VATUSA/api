<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOtsEvalsIndicatorResultsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ots_evals_indicator_results', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('perf_indicator_id');
            $table->unsignedInteger('eval_id');
            $table->smallInteger('result'); // 0 = Not Observed, 1 = Commendable, 2 = Satisfactory, 3 = Unsatisfactory
            $table->string('comment')->nullable();
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
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('ots_evals_indicator_results');
    }
}
