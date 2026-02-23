<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_list_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_completed')->default(false);
            $table->string('quantity')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_list_items');
    }
};
