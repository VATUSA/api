<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTrainingChaptersTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('training_chapters', function(Blueprint $table)
		{
			$table->bigInteger('id')->unsigned()->primary();
			$table->bigInteger('blockid')->unsigned()->index('blockid');
			$table->integer('order');
			$table->string('name');
			$table->string('url');
			$table->boolean('visible');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('training_chapters');
	}

}
