<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePromotionstestTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('promotionstest', function(Blueprint $table)
		{
			$table->integer('id')->primary();
			$table->integer('cid');
			$table->integer('grantor')->unsigned();
			$table->integer('to');
			$table->integer('from');
			$table->timestamps();
			$table->date('exam');
			$table->integer('examiner')->unsigned();
			$table->string('position', 11);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('promotionstest');
	}

}
