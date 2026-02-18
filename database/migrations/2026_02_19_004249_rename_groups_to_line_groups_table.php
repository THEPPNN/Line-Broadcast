<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('groups', 'line_groups');
    }

    public function down(): void
    {
        Schema::rename('line_groups', 'groups');
    }
};