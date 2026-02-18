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
        Schema::create('line_events', function (Blueprint $table) {
            $table->id();
        
            $table->string('event_id')->nullable()->index();
            $table->string('type')->index();
        
            $table->string('source_type')->nullable()->index();
            $table->string('user_id')->nullable()->index();
            $table->string('group_id')->nullable()->index();
            $table->string('room_id')->nullable()->index();
        
            $table->bigInteger('timestamp')->nullable()->index();
        
            $table->json('raw'); // webhook json ทั้งก้อน
        
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('line_events');
    }
};
