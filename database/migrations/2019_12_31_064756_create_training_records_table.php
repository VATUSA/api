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
            $table->time('session_duration')->nullable();
            $table->integer('num_movements')->nullable();
            $table->integer('score')->nullable();
            $table->text('notes');

            $table->smallInteger('session_location'); // 0 = Classroom, 1 = Live, 2 = Sweatbox
            $table->boolean('isOTS');
            $table->boolean('isCBT'); //CBT Completion - Auto
            $table->boolean('soloGranted');

            $table->boolean('otsResult')->nullable();

            $table->integer('edited_by')->nullable();
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
