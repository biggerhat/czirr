<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->index(['user_id', 'starts_at', 'ends_at']);
            $table->index(['recurring_event_id']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'starts_at', 'ends_at']);
            $table->dropIndex(['recurring_event_id']);
        });
    }
};
