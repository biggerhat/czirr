<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('streak_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('days_required');
            $table->unsignedInteger('bonus_points');
            $table->timestamps();

            $table->unique(['user_id', 'days_required']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streak_bonuses');
    }
};
