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
            $table->string('tmu_facility_id');
            $table->smallInteger('priority'); // 1 => Low; 2 => Standard; 3 => Urgent
            $table->string('message');
            $table->dateTime('start_date');
            $table->dateTime('expire_date')->nullable();
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
