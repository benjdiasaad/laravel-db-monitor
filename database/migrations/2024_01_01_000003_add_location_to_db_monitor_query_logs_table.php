<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('db_monitor_query_logs', function (Blueprint $table) {
            $table->string('location', 500)->nullable()->after('request_method');
        });
    }

    public function down(): void
    {
        Schema::table('db_monitor_query_logs', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
