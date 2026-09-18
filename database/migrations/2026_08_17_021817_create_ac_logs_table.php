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
        Schema::create('ac_logs', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->index();
            $table->string('active_ac');
            $table->decimal('current_ampere', 8, 4);
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ac_logs');
    }
};
