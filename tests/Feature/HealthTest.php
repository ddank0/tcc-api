<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_responde_ok(): void
    {
        $resposta = $this->getJson('/api/health');

        $resposta->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }

    public function test_health_confirma_conexao_com_o_banco(): void
    {
        $resposta = $this->getJson('/api/health');

        $resposta->assertStatus(200)
            ->assertJson(['database' => 'ok']);
    }
}
