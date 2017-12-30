<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateFlightsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('flights', function(Blueprint $table)
		{
			$table->char('callsign', 10)->unique('callsign');
			$table->string('lat', 15);
			$table->string('long', 15);
			$table->integer('hdg');
			$table->string('dest', 4);
			$table->string('dep', 4);
			$table->string('type', 8);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('flights');
	}

}
