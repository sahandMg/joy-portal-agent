<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xray_runtime_users', function (Blueprint $table) {
            $table->id();
            $table->string('inbound_tag', 100);
            $table->string('protocol', 10);
            // UUID is encrypted by the model using APP_KEY.
            $table->text('uuid');
            $table->string('email', 190)->unique();
            $table->unsignedSmallInteger('port');
            $table->unsignedSmallInteger('level')->default(0);
            $table->unsignedSmallInteger('alter_id')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'inbound_tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xray_runtime_users');
    }
};
