<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function configureAdmin(): void
    {
        config([
            'admin.email' => 'admin@example.com',
            'admin.password_hash' => Hash::make('secret123'),
        ]);
    }

    public function test_dashboard_exige_login(): void
    {
        $this->get('/admin/dashboard')
            ->assertRedirect(route('admin.login'));
    }

    public function test_credenciais_invalidas_sao_rejeitadas(): void
    {
        $this->configureAdmin();

        $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'senha-errada',
        ])->assertSessionHasErrors('email');

        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));
    }

    public function test_login_valido_abre_o_dashboard(): void
    {
        $this->configureAdmin();

        $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->get('/admin/dashboard')->assertOk();
    }

    public function test_fail_closed_sem_hash_configurado(): void
    {
        config(['admin.email' => 'admin@example.com', 'admin.password_hash' => '']);

        $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'qualquer',
        ])->assertSessionHasErrors('email');
    }
}
