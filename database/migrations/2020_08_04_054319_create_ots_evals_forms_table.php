<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOtsEvalsFormsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ots_evals_forms', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->unique();
            $table->integer('rating_id');
            $table->string('position');
            $table->text('instructor_notes')->nullable();
            $table->boolean('is_statement')->default(0);
            $table->text('description');
            $table->boolean('active')->default(1);
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
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('ots_evals_forms');
    }
}
