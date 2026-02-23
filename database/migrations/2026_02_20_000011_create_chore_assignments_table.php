<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chore_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chore_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_member_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->timestamps();

            $table->unique(['chore_id', 'family_member_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chore_assignments');
    }
};
