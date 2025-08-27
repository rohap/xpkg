<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('rule_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('text');
            $table->string('placeholder')->default('');
        });
    }

    public function down()
    {
        Schema::dropIfExists('rule_fields');
    }
};