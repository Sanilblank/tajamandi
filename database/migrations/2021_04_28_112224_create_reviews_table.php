<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReviewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onUpdate('cascade')
                  ->onDelete('cascade');
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->onUpdate('cascade')
                  ->onDelete('cascade');
            $table->integer('rating');
            $table->longText('description')->nullable();
            $table->integer('disable')->nullable();
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
        Schema::dropIfExists('reviews');
    }
}
