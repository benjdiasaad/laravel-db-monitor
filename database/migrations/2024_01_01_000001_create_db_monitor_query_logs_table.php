<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('db_monitor_query_logs', function (Blueprint $table) {

            $table->increments('id');
            
            $table->text('sql');
            
            $table->json('bindings')->nullable();
            
            $table->unsignedBigInteger('duration_ms');
            
            $table->string('connection', 50)->default('mysql');
            
            $table->string('request_id', 36)->nullable();
            
            $table->string('request_path', 500)->nullable();
            
            $table->string('request_method', 10)->nullable();
            
            $table->timestamps();

            $table->index('request_id');
            $table->index('duration_ms');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('db_monitor_query_logs');
    }
};