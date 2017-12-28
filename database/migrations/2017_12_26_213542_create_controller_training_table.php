<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateControllerTrainingTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('controller_training', function(Blueprint $table)
		{
			$table->increments('id');
			$table->integer('student_cid')->unsigned();
			$table->integer('instructor_cid')->unsigned();
			$table->string('facility', 3);
			$table->string('position', 10);
			$table->enum('type', array('Classroom','Live','Simulation','OTS Live','OTS Simulation'));
			$table->string('checklist_name');
			$table->text('checklist_data', 16777215);
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
		Schema::drop('controller_training');
	}

}
