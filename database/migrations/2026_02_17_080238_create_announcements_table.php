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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();

            // content
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('image')->nullable();

            // type
            // 1 = text
            // 2 = รูปภาพ
            $table->tinyInteger('type')->comment('1=text, 2=image');

            // schedule
            $table->timestamp('send_at')->index();

            // status
            $table->enum('status', [
                'pending',
                'sending',
                'sent',
                'failed'
            ])->default('pending')->index();

            // log
            $table->text('response')->nullable();
            $table->timestamp('sent_at')->nullable();

            // system
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
