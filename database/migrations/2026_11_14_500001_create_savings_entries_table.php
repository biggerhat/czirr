<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // family owner
            $table->foreignId('family_member_id')->constrained()->cascadeOnDelete();
            // Signed: positive = money added (owed to / saved for the member), negative = paid out or spent.
            $table->decimal('amount', 10, 2);
            $table->string('note')->nullable();
            $table->date('entry_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['family_member_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_entries');
    }
};
