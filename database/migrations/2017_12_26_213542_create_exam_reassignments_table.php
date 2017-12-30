<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateExamReassignmentsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('exam_reassignments', function(Blueprint $table)
		{
			$table->bigInteger('id', true)->unsigned();
			$table->integer('cid')->unsigned();
			$table->integer('exam_id')->unsigned();
			$table->dateTime('reassign_date');
			$table->integer('instructor_id')->unsigned();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('exam_reassignments');
	}

}
