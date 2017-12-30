<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUlsTokensTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('uls_tokens', function(Blueprint $table)
		{
			$table->string('facility', 4);
			$table->string('token', 128)->primary();
			$table->dateTime('date');
			$table->string('ip', 128);
			$table->integer('cid')->unsigned();
			$table->integer('expired')->default(0);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('uls_tokens');
	}

}
