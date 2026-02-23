<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->foreignId('linked_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->unique(['user_id', 'linked_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->dropForeign(['linked_user_id']);
            $table->dropUnique(['user_id', 'linked_user_id']);
            $table->dropColumn('linked_user_id');
        });
    }
};
