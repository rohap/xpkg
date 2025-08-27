<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::disableForeignKeyConstraints();
        Schema::create('rule_facts_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fact_id')
                ->references('id')
                ->on('rule_facts')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('field_id')
                ->references('id')
                ->on('rule_fields')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
        Schema::enableForeignKeyConstraints();
    }

    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('rule_facts_fields');
        Schema::enableForeignKeyConstraints();
    }
};