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
            $table->text('help_text')->nullable();
            $table->smallInteger('header_type')->default(0); // 0 = Not Header, 1 = Default Header, 2 = Header With Results
            $table->boolean('is_commendable')->nullable(); // 1 = Can Commend
            $table->boolean('is_required')->nullable(); // 1 = Result Required
            $table->boolean('can_unsat')->nullable(); // 1 = Result can Unsat
            $table->string('extra_options')->nullable();
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
