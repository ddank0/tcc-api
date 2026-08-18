<?php

namespace Tests\Feature;

use App\Console\Commands\ExportarOpenApi;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class OpenApiTest extends TestCase
{
    public function test_o_arquivo_versionado_esta_atualizado(): void
    {
        // Sem esta verificação o openapi.json diverge do código em silêncio, e
        // o cliente Angular passa a ser gerado de um contrato falso.
        $saida = $this->artisan('openapi:export --check');

        if ($saida instanceof PendingCommand) {
            $saida->assertSuccessful();

            return;
        }

        $this->assertSame(0, $saida);
    }

    public function test_a_especificacao_declara_todos_os_endpoints(): void
    {
        /** @var ExportarOpenApi $comando */
        $comando = app(ExportarOpenApi::class);
        /** @var array{paths: array<string, mixed>} $spec */
        $spec = json_decode($comando->gerar(), true);

        $esperados = [
            '/health', '/licitacoes', '/licitacoes/{id}',
            '/analytics/evolucao', '/analytics/modalidades',
            '/analytics/orgaos', '/analytics/fornecedores',
        ];

        foreach ($esperados as $caminho) {
            $this->assertArrayHasKey($caminho, $spec['paths'], "faltou {$caminho}");
        }
    }

    public function test_openapi_json_e_servido(): void
    {
        $this->get('/api/openapi.json')->assertOk();
    }

    public function test_docs_renderiza(): void
    {
        $this->get('/api/docs')->assertOk()->assertSee('swagger', false);
    }

    public function test_a_especificacao_e_json_valido(): void
    {
        $conteudo = (string) file_get_contents(base_path('openapi.json'));

        $this->assertNotNull(json_decode($conteudo), 'openapi.json não é JSON válido');
    }
}
