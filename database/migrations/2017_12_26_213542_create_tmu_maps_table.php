<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTmuMapsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('tmu_maps', function(Blueprint $table)
		{
			$table->integer('id')->primary();
			$table->string('parent_facility', 4);
			$table->string('facilities');
			$table->string('name', 64);
			$table->text('data', 16777215);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('tmu_maps');
	}

}
