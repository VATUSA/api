<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBucketlogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bucketlogs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer("bucket_id");
            $table->string("facility");
            $table->integer("cid");
            $table->string("ip");
            $table->mediumText("log");
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
        Schema::dropIfExists('bucketlogs');
    }
}
