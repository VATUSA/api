<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTrainingBlocksTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('training_blocks', function(Blueprint $table)
		{
			$table->bigInteger('id')->unsigned()->primary();
			$table->string('facility', 3)->comment('Facility ID, ZAE = VATUSA Academy');
			$table->integer('order');
			$table->string('name');
			$table->enum('level', array('Senior Staff','Staff','I1','C1','S1','ALL'))->default('ALL');
			$table->boolean('visible')->default(1);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('training_blocks');
	}

}
