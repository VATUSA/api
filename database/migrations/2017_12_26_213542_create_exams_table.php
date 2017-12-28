<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateExamsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('exams', function(Blueprint $table)
		{
			$table->increments('id');
			$table->string('facility_id', 4);
			$table->string('name');
			$table->integer('number');
			$table->integer('is_active')->default(1)->comment('0 - no, 1 - yes');
			$table->bigInteger('cbt_required')->unsigned()->nullable()->default(0);
			$table->integer('retake_period')->default(7)->comment('number of days');
			$table->integer('passing_score')->default(70);
			$table->enum('answer_visibility', array('none','user_only','all','all_passed'));
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('exams');
	}

}
