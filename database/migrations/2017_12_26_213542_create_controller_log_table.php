<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateControllerLogTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('controller_log', function(Blueprint $table)
		{
			$table->integer('cid')->unsigned();
			$table->timestamp('datetime')->default(DB::raw('CURRENT_TIMESTAMP'));
			$table->integer('submitter')->unsigned();
			$table->text('entry', 16777215);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('controller_log');
	}

}
