<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('graph')->nullable();
            $table->text('dsl')->nullable();
            $table->text('txt')->nullable();
            $table->unsignedInteger('salience')->default(5);
            $table->timestamps();
            $table->softDeletes('disabled_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('rules');
    }
};