<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sftp_activities', function (Blueprint $table) {
            $table->id();
            $table->char('event_key', 64)->unique();
            $table->string('action', 32);
            $table->string('username');
            $table->text('path');
            $table->text('target_path')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('protocol', 32)->nullable();
            $table->dateTime('occurred_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sftp_activities');
    }
};
