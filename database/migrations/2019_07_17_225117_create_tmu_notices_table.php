<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTmuNoticesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tmu_notices', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('tmu_facility_id');
            $table->smallInteger('priority'); // 0 => Low; 1 => Standard; 2 => Urgent
            $table->string('message');
            $table->dateTime('expire_date');
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
        Schema::dropIfExists('tmu_notices');
    }
}
