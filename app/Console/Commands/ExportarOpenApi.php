<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenApi\Generator;
use RuntimeException;

class ExportarOpenApi extends Command
{
    protected $signature = 'openapi:export {--check : Só verifica se o arquivo está atualizado}';

    protected $description = 'Gera openapi.json a partir dos atributos dos controllers';

    /**
     * O arquivo é versionado, não gerado em runtime: o cliente TypeScript do
     * Angular é gerado dele, e o frontend lê ../tcc-api/openapi.json em
     * desenvolvimento.
     */
    public function handle(): int
    {
        $gerado = $this->gerar();
        $caminho = base_path('openapi.json');

        if ($this->option('check')) {
            $atual = file_exists($caminho) ? (string) file_get_contents($caminho) : '';

            if ($atual !== $gerado) {
                $this->error('openapi.json está desatualizado. Rode `php artisan openapi:export`.');

                return self::FAILURE;
            }

            $this->info('openapi.json está atualizado.');

            return self::SUCCESS;
        }

        file_put_contents($caminho, $gerado);
        $this->info("openapi.json exportado em {$caminho}");

        return self::SUCCESS;
    }

    public function gerar(): string
    {
        // Na 6.x o gerador é de instância; Generator::scan() é da 4.x.
        $openapi = (new Generator)->generate([app_path()]);

        if ($openapi === null) {
            throw new RuntimeException('não foi possível gerar a especificação a partir dos atributos');
        }

        return $openapi->toJson()."\n";
    }
}
