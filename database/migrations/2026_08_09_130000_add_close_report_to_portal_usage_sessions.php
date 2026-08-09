<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_usage_sessions', function (Blueprint $table) {
            $table->timestamp('closed_reported_at')->nullable()->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('portal_usage_sessions', function (Blueprint $table) {
            $table->dropColumn('closed_reported_at');
        });
    }
};
