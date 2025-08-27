<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('rule_facts', function (Blueprint $table) {
            $table->id();
            $table->string('fact');
            $table->string('name');
            $table->string('type');
            $table->boolean('use_defaults')->default(1);
        });
    }

    public function down()
    {
        Schema::dropIfExists('rule_facts');
    }
};