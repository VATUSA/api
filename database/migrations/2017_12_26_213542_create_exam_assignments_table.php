<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateExamAssignmentsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('exam_assignments', function(Blueprint $table)
		{
			$table->bigInteger('id', true)->unsigned();
			$table->integer('cid')->unsigned();
			$table->integer('exam_id')->unsigned();
			$table->integer('instructor_id')->unsigned();
			$table->dateTime('assigned_date');
			$table->dateTime('expire_date');
			$table->index(['cid','exam_id'], 'cid');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('exam_assignments');
	}

}
