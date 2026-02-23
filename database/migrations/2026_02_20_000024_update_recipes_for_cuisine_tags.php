<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->foreignId('cuisine_id')->nullable()->after('source_url')->constrained('cuisines')->nullOnDelete();
            $table->dropColumn(['cuisine', 'tags']);
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cuisine_id');
            $table->string('cuisine')->nullable();
            $table->json('tags')->nullable();
        });
    }
};
