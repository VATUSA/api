<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateExamGeneratedTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('exam_generated', function(Blueprint $table)
		{
			$table->increments('id');
			$table->integer('cid')->unsigned();
			$table->integer('exam_id')->unsigned();
			$table->integer('question_id')->unsigned();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('exam_generated');
	}

}
