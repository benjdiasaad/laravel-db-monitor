<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('db_monitor_findings', function (Blueprint $table) {
            $table->increments('id');

            $table->string('type', 50);
            
            $table->string('severity', 20);
            
            $table->text('message');
            
            $table->json('context')->nullable();
            
            $table->string('request_path', 500)->nullable();
            
            $table->boolean('notified')->default(false);
            
            $table->timestamps();

            $table->index('type');
            $table->index('severity');
            $table->index('notified');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('db_monitor_findings');
    }
};