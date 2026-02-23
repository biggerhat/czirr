<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->text('rrule')->nullable()->after('is_all_day');
            $table->foreignId('recurring_event_id')->nullable()->after('rrule')
                ->constrained('events')->cascadeOnDelete();
            $table->dateTime('original_start')->nullable()->after('recurring_event_id');
            $table->json('recurrence_exceptions')->nullable()->after('original_start');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['recurring_event_id']);
            $table->dropColumn(['rrule', 'recurring_event_id', 'original_start', 'recurrence_exceptions']);
        });
    }
};
