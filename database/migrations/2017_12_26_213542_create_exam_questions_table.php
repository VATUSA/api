<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateExamQuestionsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('exam_questions', function(Blueprint $table)
		{
			$table->increments('id');
			$table->integer('exam_id')->unsigned();
			$table->text('question', 65535);
			$table->integer('type')->comment('0 - multiple choice, 1 - true/false');
			$table->string('answer');
			$table->string('alt1');
			$table->string('alt2');
			$table->string('alt3');
			$table->string('notes')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('exam_questions');
	}

}
