<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateMembershipsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('memberships', function(Blueprint $table)
		{
			$table->integer('cid')->unsigned()->primary();
			$table->integer('rating')->unsigned();
			$table->string('facility_id', 3);
			$table->integer('type')->comment('1 - Home, 2- Visitor');
			$table->dateTime('joined');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('memberships');
	}

}
