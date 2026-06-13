<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('game_logs')) {
            return;
        }

        Schema::create('game_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('level');           // 1–7
            $table->string('grid', 10);                     // ex.: "4×4"
            $table->unsignedSmallInteger('time_seconds');   // tempo da partida
            $table->unsignedSmallInteger('moves');          // pares virados
            $table->unsignedSmallInteger('errors');         // tentativas erradas
            $table->unsignedSmallInteger('hits');           // pares encontrados
            $table->string('score', 5);                     // S, A+, A, B, C
            $table->string('status', 20)->default('completed'); // completed | abandoned
            $table->string('ip_address', 45)->nullable();   // IPv4 ou IPv6
            $table->string('user_agent')->nullable();
            $table->string('session_id', 64)->nullable();
            $table->timestamps();                           // created_at = momento da partida

            $table->index('level');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_logs');
    }
};
