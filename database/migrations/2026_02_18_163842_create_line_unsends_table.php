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
        Schema::create('line_unsends', function (Blueprint $table) {
            $table->id();
        
            $table->string('message_id')->index();
            $table->string('user_id')->nullable();
            $table->string('group_id')->nullable();
        
            $table->timestamp('unsent_at');
        
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('line_unsends');
    }
};
