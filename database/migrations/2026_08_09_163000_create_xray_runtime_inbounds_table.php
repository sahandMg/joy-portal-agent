<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xray_runtime_inbounds', function (Blueprint $table) {
            $table->id();
            $table->string('tag', 100)->unique();
            $table->string('protocol', 10);
            $table->string('transport', 10);
            $table->unsignedSmallInteger('port');
            $table->string('ws_path', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'port']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xray_runtime_inbounds');
    }
};
