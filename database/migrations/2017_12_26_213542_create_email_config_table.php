<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateEmailConfigTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('email_config', function(Blueprint $table)
		{
			$table->string('address')->unique('address');
			$table->enum('config', array('user','static'));
			$table->string('destination')->nullable();
			$table->integer('modified_by');
			$table->dateTime('updated_at');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('email_config');
	}

}
