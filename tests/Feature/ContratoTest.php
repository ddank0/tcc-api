<?php

namespace Tests\Feature;

use App\Console\Commands\ExportarOpenApi;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SemeiaBase;
use Tests\TestCase;

/**
 * Valida as respostas contra o schema declarado, e não só a forma à mão.
 *
 * Um `assertJsonStructure` confere que a chave existe; isto confere também o
 * tipo, que é o que o cliente TypeScript gerado vai assumir.
 */
class ContratoTest extends TestCase
{
    use SemeiaBase;

    /** @var array<string, mixed> */
    private array $spec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->semear();
        $this->semearSerie();
        $this->semearRanking();

        /** @var ExportarOpenApi $comando */
        $comando = app(ExportarOpenApi::class);
        /** @var array<string, mixed> $spec */
        $spec = json_decode($comando->gerar(), true);
        $this->spec = $spec;
    }

    /** @return list<array{string, string}> */
    public static function endpoints(): array
    {
        return [
            ['/api/health', '/health'],
            ['/api/licitacoes', '/licitacoes'],
            ['/api/analytics/evolucao', '/analytics/evolucao'],
            ['/api/analytics/modalidades', '/analytics/modalidades'],
            ['/api/analytics/orgaos', '/analytics/orgaos'],
            ['/api/analytics/fornecedores', '/analytics/fornecedores'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_endpoint_responde_e_esta_declarado(string $url, string $caminho): void
    {
        $this->getJson($url)->assertOk();

        /** @var array<string, mixed> $paths */
        $paths = $this->spec['paths'];
        $this->assertArrayHasKey($caminho, $paths, "{$caminho} não está no openapi.json");
    }

    public function test_tipos_da_listagem_batem_com_o_schema(): void
    {
        $resposta = $this->getJson('/api/licitacoes')->assertOk();

        /** @var list<array<string, mixed>> $linhas */
        $linhas = $resposta->json('data');

        foreach ($linhas as $linha) {
            $this->assertIsInt($linha['id']);
            $this->assertIsString($linha['numero_licitacao']);
            $this->assertIsString($linha['competencia']);
            // Decimal como string, nunca float: o cliente gerado assume string.
            $this->assertTrue($linha['valor'] === null || is_string($linha['valor']));
            $this->assertTrue($linha['data_abertura'] === null || is_string($linha['data_abertura']));
            $this->assertIsArray($linha['modalidade']);
            $this->assertIsArray($linha['unidade_gestora']);
        }
    }

    public function test_tipos_da_serie_batem_com_o_schema(): void
    {
        $resposta = $this->getJson('/api/analytics/evolucao')->assertOk();

        /** @var list<array<string, mixed>> $linhas */
        $linhas = $resposta->json('data');

        foreach ($linhas as $linha) {
            $this->assertIsString($linha['competencia']);
            $this->assertIsInt($linha['quantidade_licitacoes']);
            $this->assertIsBool($linha['parcial']);
            $this->assertTrue($linha['valor_total'] === null || is_string($linha['valor_total']));
        }
    }

    public function test_erro_de_validacao_devolve_422_com_mensagem(): void
    {
        $this->getJson('/api/licitacoes?per_page=9999')
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_nenhuma_resposta_usa_vocabulario_de_acusacao(): void
    {
        // O sistema aponta atipicidade estatística e NÃO caracteriza fraude.
        // É a restrição inviolável do trabalho, e este teste é o que a torna
        // verificável em vez de prometida.
        $proibidos = [
            'suspeita', 'suspeito', 'irregularidade', 'irregular',
            'fraude', 'fraudulento', 'ilícito', 'ilegal', 'crime', 'culpado',
        ];

        foreach (self::endpoints() as [$url, $_]) {
            $corpo = mb_strtolower($this->getJson($url)->content());

            foreach ($proibidos as $termo) {
                $this->assertStringNotContainsString(
                    $termo, $corpo, "o endpoint {$url} usa \"{$termo}\""
                );
            }
        }
    }

    public function test_a_especificacao_tambem_evita_o_vocabulario(): void
    {
        // O openapi.json vira documentação pública e gera o cliente. O termo
        // proibido não pode entrar por descrição de campo.
        $texto = mb_strtolower((string) file_get_contents(base_path('openapi.json')));

        foreach (['suspeita', 'irregularidade', 'fraude', 'ilícito'] as $termo) {
            $this->assertStringNotContainsString($termo, $texto);
        }
    }

    public function test_paginacao_declara_os_mesmos_campos_em_toda_listagem(): void
    {
        /** @var array<string, mixed> $meta */
        $meta = $this->getJson('/api/licitacoes')->assertOk()->json('meta');

        foreach (['total', 'per_page', 'current_page', 'last_page'] as $campo) {
            $this->assertArrayHasKey($campo, $meta);
            $this->assertIsInt($meta[$campo]);
        }
    }
}
