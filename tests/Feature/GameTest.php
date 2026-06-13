<?php

namespace Tests\Feature;

use Tests\TestCase;

class GameTest extends TestCase
{
    public function test_pagina_do_jogo_carrega(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Memória', false)
            ->assertSee('window.LEVELS', false);
    }
}
