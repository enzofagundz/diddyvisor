<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('house_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('invited_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->index(['house_id', 'email']);
            $table->index('invited_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
