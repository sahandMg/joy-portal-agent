<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xray_usage_samples', function (Blueprint $table) {
            $table->id();
            $table->uuid('collection_id')->index();
            $table->string('email')->index();
            $table->unsignedBigInteger('uplink_total_bytes')->default(0);
            $table->unsignedBigInteger('downlink_total_bytes')->default(0);
            $table->unsignedBigInteger('uplink_delta_bytes')->default(0);
            $table->unsignedBigInteger('downlink_delta_bytes')->default(0);
            $table->boolean('counter_reset_detected')->default(false);
            $table->timestamp('observed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xray_usage_samples');
    }
};
