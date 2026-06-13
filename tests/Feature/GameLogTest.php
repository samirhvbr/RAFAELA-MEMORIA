<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_grava_log_de_partida_valido(): void
    {
        $payload = [
            'level' => 3,
            'grid' => '4×4',
            'time_seconds' => 42,
            'moves' => 10,
            'errors' => 2,
            'hits' => 8,
            'score' => 'A',
            'status' => 'completed',
            'session_id' => 'sess-abc-123',
        ];

        $this->postJson('/api/log', $payload)
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('game_logs', [
            'level' => 3,
            'score' => 'A',
            'status' => 'completed',
        ]);
    }

    public function test_rejeita_payload_invalido(): void
    {
        $this->postJson('/api/log', ['level' => 99, 'score' => 'Z'])
            ->assertStatus(422);

        $this->assertDatabaseCount('game_logs', 0);
    }

    public function test_ignora_ip_enviado_pelo_cliente(): void
    {
        $this->postJson('/api/log', [
            'level' => 1,
            'grid' => '2×2',
            'time_seconds' => 5,
            'moves' => 2,
            'errors' => 0,
            'hits' => 2,
            'score' => 'S',
            'status' => 'completed',
            'ip_address' => '6.6.6.6',   // tentativa de injeção — deve ser ignorada
        ])->assertOk();

        $this->assertDatabaseMissing('game_logs', ['ip_address' => '6.6.6.6']);
    }
}
