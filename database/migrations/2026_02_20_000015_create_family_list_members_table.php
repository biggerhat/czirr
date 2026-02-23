<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_list_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_member_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['family_list_id', 'family_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_list_members');
    }
};
