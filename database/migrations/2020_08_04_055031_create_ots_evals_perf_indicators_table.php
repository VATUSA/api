<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOtsEvalsPerfIndicatorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ots_evals_perf_indicators', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('perf_cat_id');
            $table->string('label');
            $table->string('help_text')->nullable();
            $table->boolean('is_header')->default(0);
            $table->boolean('is_commendable')->default(1); // 1 = Can Commend
            $table->boolean('is_required')->default(1); // 1 = Result Required
            $table->boolean('can_unsat')->default(1); // 1 = Result can Unsat
            $table->integer("order");

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
        Schema::dropIfExists('ots_evals_perf_indicators');
    }
}
