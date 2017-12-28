<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateFacilityTrendsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('facility_trends', function(Blueprint $table)
		{
			$table->bigInteger('id')->unsigned()->primary();
			$table->date('date');
			$table->string('facility', 4);
			$table->integer('obs');
			$table->integer('obsg30');
			$table->integer('s1');
			$table->integer('s2');
			$table->integer('s3');
			$table->integer('c1');
			$table->integer('c3');
			$table->integer('i1')->comment('I1 or greater');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('facility_trends');
	}

}
