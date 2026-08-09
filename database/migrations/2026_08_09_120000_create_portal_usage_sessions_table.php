<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_usage_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->index();
            $table->uuid('session_id')->unique();
            $table->unsignedBigInteger('sequence')->default(0);
            $table->unsignedBigInteger('total_bytes')->default(0);
            $table->unsignedBigInteger('last_reported_total_bytes')->default(0);
            $table->timestamp('last_activity_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_reported_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['email', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_usage_sessions');
    }
};
