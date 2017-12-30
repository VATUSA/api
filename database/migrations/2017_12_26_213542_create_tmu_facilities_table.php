<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTmuFacilitiesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('tmu_facilities', function(Blueprint $table)
		{
			$table->string('id', 4)->unique('id');
			$table->string('parent', 4)->nullable();
			$table->string('name');
			$table->text('coords', 65535);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('tmu_facilities');
	}

}
