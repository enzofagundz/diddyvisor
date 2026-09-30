<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('house_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->date('competence');
            $table->date('due_date');
            $table->bigInteger('total_cents');
            $table->smallInteger('status')->default(0);
            $table->timestampsTz();
            $table->index(['house_id', 'competence', 'due_date', 'id']);
        });
        Schema::create('bill_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_cents');
            $table->boolean('is_paid')->default(false);
            $table->timestampsTz();
            $table->unique(['bill_id', 'membership_id']);
            $table->index('membership_id');
        });
        DB::statement('ALTER TABLE bills ADD CONSTRAINT bills_values_check CHECK (total_cents > 0 AND status IN (0,1,2) AND EXTRACT(DAY FROM competence) = 1)');
        DB::statement('ALTER TABLE bill_shares ADD CONSTRAINT bill_shares_values_check CHECK (amount_cents >= 0 AND (NOT is_paid OR amount_cents > 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_shares');
        Schema::dropIfExists('bills');
    }
};
