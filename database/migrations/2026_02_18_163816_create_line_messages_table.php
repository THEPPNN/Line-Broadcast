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
        Schema::create('line_messages', function (Blueprint $table) {
            $table->id();
        
            $table->string('message_id')->unique();
            $table->string('type')->index();
            $table->string('user_id')->nullable()->index();
            $table->string('user_name')->nullable();
            $table->string('group_id')->nullable()->index();
            $table->string('room_id')->nullable()->index();
        
            $table->text('text')->nullable();
        
            $table->string('file_url')->nullable();
            $table->string('file_type')->nullable();
        
            $table->boolean('is_unsent')->default(false)->index();
            $table->timestamp('unsent_at')->nullable();
        
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('line_messages');
    }
};
