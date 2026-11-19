<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grid_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // family owner
            $table->string('kind', 20); // tictactoe | connect4
            $table->foreignId('player_one_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('player_two_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('current_turn_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('board'); // flat row-major; null or 1 (player one) / 2 (player two)
            $table->string('status', 20)->default('active'); // active | won | draw
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grid_games');
    }
};
