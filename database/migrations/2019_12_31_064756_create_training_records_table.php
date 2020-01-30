<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTrainingRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('training_records', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('student_id');
            $table->integer('instructor_id');

            $table->datetime('session_date');

            $table->string('facility_id');
            $table->string('position');
            $table->time('duration');
            $table->integer('num_movements')->nullable();
            $table->integer('score')->nullable();
            $table->text('notes');

            $table->smallInteger('location'); // 0 = Classroom, 1 = Live, 2 = Sweatbox
            $table->boolean('is_ots');
            $table->boolean('is_cbt'); //CBT Completion - Auto
            $table->boolean('solo_granted');

            $table->boolean('ots_result')->nullable();

            $table->integer('modified_by')->nullable();
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
        Schema::dropIfExists('training_records');
    }
}
