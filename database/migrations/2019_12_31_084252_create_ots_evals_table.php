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
            $table->integer('training_record_id')->nullable();
            $table->integer('student_id');
            $table->integer('instructor_id');
            $table->string('facility_id');
            $table->unsignedInteger('form_id');
            $table->text('notes');
            $table->boolean('result'); // 0 = Fail, 1 = Pass
            $table->text('signature');

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
        Schema::dropIfExists('ots_evals');
    }
}
