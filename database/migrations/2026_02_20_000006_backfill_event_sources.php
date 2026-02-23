<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('events')
            ->whereIn('id', DB::table('bills')->whereNotNull('event_id')->pluck('event_id'))
            ->update(['source' => 'bill']);

        DB::table('events')
            ->whereIn('id', DB::table('incomes')->whereNotNull('event_id')->pluck('event_id'))
            ->update(['source' => 'income']);
    }

    public function down(): void
    {
        DB::table('events')
            ->whereIn('source', ['bill', 'income'])
            ->update(['source' => null]);
    }
};
