<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chores', function (Blueprint $table) {
            $table->boolean('requires_approval')->default(false)->after('points');
        });

        Schema::table('chore_completions', function (Blueprint $table) {
            // Existing rows were all counted, so they default to approved.
            $table->string('status', 16)->default('approved')->after('points_earned');
            $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::table('chore_completions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['status', 'approved_at']);
        });

        Schema::table('chores', function (Blueprint $table) {
            $table->dropColumn('requires_approval');
        });
    }
};
