<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('two_factor_settings', function (Blueprint $table) {
            $table->id();
            $table->string('user_nip')->unique();
            $table->boolean('is_enabled')->default(false);
            $table->text('secret_key')->nullable();
            $table->text('recovery_codes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_settings');
    }
};
