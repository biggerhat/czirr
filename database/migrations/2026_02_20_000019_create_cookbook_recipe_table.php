<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cookbook_recipe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cookbook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['cookbook_id', 'recipe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookbook_recipe');
    }
};
