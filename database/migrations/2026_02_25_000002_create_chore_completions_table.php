<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chore_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chore_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_member_id')->constrained()->cascadeOnDelete();
            $table->date('completed_date');
            $table->unsignedInteger('points_earned');
            $table->timestamps();

            $table->unique(['chore_assignment_id', 'completed_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chore_completions');
    }
};
