<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOtsEvalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ots_evals', function (Blueprint $table) {
            $table->increments('id');
            $table->string('filename'); //Randomly generated and short
            $table->integer('training_record_id')->nullable();
            $table->integer('student_id');
            $table->integer('instructor_id');
            $table->smallInteger('eval_level'); //1 = S1, 2 = S2, 3 = S3, 4 = C1
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
        Schema::dropIfExists('ots_evals');
    }
}
