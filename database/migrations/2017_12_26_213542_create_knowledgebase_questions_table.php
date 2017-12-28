<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateKnowledgebaseQuestionsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('knowledgebase_questions', function(Blueprint $table)
		{
			$table->integer('id')->unsigned()->primary();
			$table->integer('category_id')->unsigned();
			$table->integer('order')->unsigned();
			$table->text('question', 65535);
			$table->text('answer', 16777215);
			$table->integer('updated_by');
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
		Schema::drop('knowledgebase_questions');
	}

}
