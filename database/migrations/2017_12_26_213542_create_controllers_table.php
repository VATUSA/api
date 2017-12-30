<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateControllersTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('controllers', function(Blueprint $table)
		{
			$table->integer('cid')->unsigned()->index('cid');
			$table->string('fname', 100)->index('fname');
			$table->string('lname', 100)->index('lname');
			$table->string('email');
			$table->string('facility', 4);
			$table->integer('rating')->index('rating');
			$table->timestamps();
			$table->integer('flag_needbasic')->default(0);
			$table->integer('flag_xferOverride')->default(0);
			$table->dateTime('facility_join');
			$table->integer('flag_homecontroller');
			$table->string('remember_token')->nullable();
			$table->integer('cert_update')->default(0);
			$table->dateTime('lastactivity');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('controllers');
	}

}
