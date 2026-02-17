<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_logs', function (Blueprint $table) {
            $table->id();

            // relation
            $table->foreignId('announcement_id')
                ->constrained('announcements')
                ->cascadeOnDelete();

            $table->string('group_id')->index();

            // status tracking
            $table->enum('status', [
                'pending',
                'sent',
                'failed'
            ])->default('pending')->index();

            // response debug
            $table->text('response')->nullable();
            $table->text('error')->nullable();

            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            // prevent duplicate send same group
            $table->unique(['announcement_id','group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_logs');
    }
};