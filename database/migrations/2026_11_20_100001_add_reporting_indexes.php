<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Budget month views and scoring/streak queries filter by owner/member + date range.
        Schema::table('expenses', fn (Blueprint $t) => $t->index(['user_id', 'date'], 'expenses_user_date_idx'));
        Schema::table('chore_completions', fn (Blueprint $t) => $t->index(['family_member_id', 'completed_date'], 'completions_member_date_idx'));
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $t) => $t->dropIndex('expenses_user_date_idx'));
        Schema::table('chore_completions', fn (Blueprint $t) => $t->dropIndex('completions_member_date_idx'));
    }
};
