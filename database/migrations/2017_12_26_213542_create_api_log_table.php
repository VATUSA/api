<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateApiLogTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('api_log', function(Blueprint $table)
		{
			$table->bigInteger('id', true)->unsigned();
			$table->string('facility', 3)->index('facility');
			$table->dateTime('datetime');
			$table->string('method', 7);
			$table->string('url');
			$table->string('data');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('api_log');
	}

}
