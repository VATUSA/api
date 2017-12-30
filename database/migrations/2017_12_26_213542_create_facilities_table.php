<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateFacilitiesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('facilities', function(Blueprint $table)
		{
			$table->char('id', 3)->unique('id');
			$table->string('name');
			$table->string('url');
			$table->integer('region');
			$table->integer('atm')->unsigned();
			$table->integer('datm')->unsigned();
			$table->integer('ta')->unsigned();
			$table->integer('ec')->unsigned();
			$table->integer('fe')->unsigned();
			$table->integer('wm')->unsigned();
			$table->string('uls_return');
			$table->string('uls_devreturn');
			$table->string('uls_secret', 16);
			$table->text('uls_jwk');
			$table->integer('active')->default(1);
			$table->string('apikey', 25);
			$table->string('ip', 128);
			$table->string('api_sandbox_key');
			$table->string('api_sandbox_ip', 128);
			$table->text('welcome_text', 16777215);
			$table->integer('ace');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('facilities');
	}

}
