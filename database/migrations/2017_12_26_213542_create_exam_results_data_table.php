<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateExamResultsDataTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('exam_results_data', function(Blueprint $table)
		{
			$table->increments('id');
			$table->bigInteger('result_id')->unsigned();
			$table->string('question');
			$table->string('correct');
			$table->string('selected')->nullable();
			$table->string('notes');
			$table->integer('is_correct');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('exam_results_data');
	}

}
