<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAcademyExamAssignmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('academy_exam_assignments', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('student_id');
            $table->integer('instructor_id');
            $table->integer('moodle_uid');
            $table->integer('course_id');
            $table->string('course_name');
            $table->integer('quiz_id');
            $table->integer('rating_id');
            $table->string('attempt_emails_sent')->nullable();
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
        Schema::dropIfExists('academy_exam_assignments');
    }
}
