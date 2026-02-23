<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('ingredients');
            $table->text('instructions');
            $table->unsignedInteger('prep_time');
            $table->unsignedInteger('cook_time');
            $table->unsignedInteger('servings');
            $table->string('image_url')->nullable();
            $table->string('source_url')->nullable();
            $table->string('cuisine')->nullable();
            $table->string('difficulty');
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
