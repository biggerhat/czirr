<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Bills: due_day → start_date ---
        Schema::table('bills', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('due_day');
        });

        $now = now();
        $year = $now->year;
        $month = $now->month;
        $maxDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        DB::table('bills')->orderBy('id')->each(function ($bill) use ($year, $month, $maxDay) {
            $day = min($bill->due_day, $maxDay);
            DB::table('bills')->where('id', $bill->id)->update([
                'start_date' => sprintf('%04d-%02d-%02d', $year, $month, $day),
            ]);
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->date('start_date')->nullable(false)->change();
            $table->dropColumn('due_day');
        });

        // --- Incomes: pay_day → start_date ---
        Schema::table('incomes', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('pay_day');
        });

        DB::table('incomes')->orderBy('id')->each(function ($income) use ($year, $month, $maxDay) {
            $day = min($income->pay_day, $maxDay);
            DB::table('incomes')->where('id', $income->id)->update([
                'start_date' => sprintf('%04d-%02d-%02d', $year, $month, $day),
            ]);
        });

        Schema::table('incomes', function (Blueprint $table) {
            $table->date('start_date')->nullable(false)->change();
            $table->dropColumn('pay_day');
        });
    }

    public function down(): void
    {
        // --- Bills: start_date → due_day ---
        Schema::table('bills', function (Blueprint $table) {
            $table->unsignedTinyInteger('due_day')->default(1)->after('amount');
        });

        DB::table('bills')->orderBy('id')->each(function ($bill) {
            $day = (int) date('j', strtotime($bill->start_date));
            DB::table('bills')->where('id', $bill->id)->update(['due_day' => $day]);
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn('start_date');
        });

        // --- Incomes: start_date → pay_day ---
        Schema::table('incomes', function (Blueprint $table) {
            $table->unsignedTinyInteger('pay_day')->default(1)->after('amount');
        });

        DB::table('incomes')->orderBy('id')->each(function ($income) {
            $day = (int) date('j', strtotime($income->start_date));
            DB::table('incomes')->where('id', $income->id)->update(['pay_day' => $day]);
        });

        Schema::table('incomes', function (Blueprint $table) {
            $table->dropColumn('start_date');
        });
    }
};
