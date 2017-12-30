<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTransfersTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('transfers', function(Blueprint $table)
		{
			$table->integer('id')->unsigned()->primary();
			$table->integer('cid')->unsigned();
			$table->string('to', 3);
			$table->string('from', 3);
			$table->text('reason', 65535);
			$table->integer('status')->comment('0-pending,1-accepted,2-rejected');
			$table->text('actiontext', 65535);
			$table->integer('actionby')->unsigned();
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
		Schema::drop('transfers');
	}

}
