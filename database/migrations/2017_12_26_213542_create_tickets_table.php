<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTicketsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('tickets', function(Blueprint $table)
		{
			$table->integer('id')->unsigned()->primary();
			$table->integer('cid');
			$table->string('subject');
			$table->text('body', 16777215);
			$table->enum('status', array('Open','Closed'));
			$table->char('facility', 3);
			$table->string('assigned_to');
			$table->text('notes', 16777215);
			$table->enum('priority', array('Low','Normal','High'));
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
		Schema::drop('tickets');
	}

}
