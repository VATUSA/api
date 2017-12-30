<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTicketsNotesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('tickets_notes', function(Blueprint $table)
		{
			$table->integer('id')->unsigned()->primary();
			$table->integer('ticket_id');
			$table->integer('cid');
			$table->text('note', 16777215);
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
		Schema::drop('tickets_notes');
	}

}
