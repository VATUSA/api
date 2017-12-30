<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSoloCertsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('solo_certs', function(Blueprint $table)
		{
			$table->integer('id')->unsigned()->primary();
			$table->integer('cid')->unsigned();
			$table->string('position');
			$table->date('expires');
			$table->timestamps();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('solo_certs');
	}

}
