<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('houses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestampsTz();
        });
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('house_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->smallInteger('role')->default(0);
            $table->string('display_name');
            $table->timestampTz('left_at')->nullable();
            $table->timestampsTz();
            $table->index(['house_id', 'left_at']);
            $table->index(['user_id', 'left_at']);
        });
        DB::statement('CREATE UNIQUE INDEX memberships_active_house_user_unique ON memberships (house_id, user_id) WHERE left_at IS NULL');
        DB::statement('ALTER TABLE memberships ADD CONSTRAINT memberships_role_check CHECK (role IN (0, 1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('houses');
    }
};
