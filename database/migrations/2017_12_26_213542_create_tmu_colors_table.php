<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTmuColorsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('tmu_colors', function(Blueprint $table)
		{
			$table->string('id', 4)->unique('id');
			$table->text('black', 65535)->nullable();
			$table->text('brown', 65535)->nullable()->comment('1');
			$table->text('blue', 65535)->nullable()->comment('2');
			$table->text('gray', 65535)->nullable()->comment('3');
			$table->text('green', 65535)->nullable()->comment('4');
			$table->text('lime', 65535)->nullable()->comment('5');
			$table->text('cyan', 65535)->nullable()->comment('6');
			$table->text('orange', 65535)->nullable()->comment('7');
			$table->text('red', 65535)->nullable()->comment('9');
			$table->text('purple', 65535)->nullable()->comment('10');
			$table->text('white', 65535)->nullable()->comment('11');
			$table->text('yellow', 65535)->nullable()->comment('12');
			$table->text('violet', 65535)->nullable()->comment('13');
			$table->text('guide', 65535)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('tmu_colors');
	}

}
