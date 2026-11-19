<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // family owner
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('kind', 20)->default('scorekeeper'); // scorekeeper | farkle
            $table->json('players'); // [{name, family_member_id?}]
            $table->json('rounds'); // list of per-player score lists
            $table->unsignedInteger('target_score')->nullable();
            $table->boolean('low_wins')->default(false);
            $table->string('status', 20)->default('active');
            $table->string('winner_names')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_games');
    }
};
