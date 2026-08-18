<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListarLicitacoesRequest;
use App\Http\Resources\LicitacaoDetalheResource;
use App\Http\Resources\LicitacaoResource;
use App\Models\Licitacao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LicitacaoController extends Controller
{
    /** Teto de itens e participantes por resposta de detalhe. */
    private const FILHOS_MAXIMO = 500;

    /**
     * Listagem paginada e filtrada.
     *
     * Medido na base de 1,74 milhão de licitações: 46 ms com cache quente,
     * 2.348 ms com cache frio. O `select` explícito e o eager loading são o
     * que mantém isso - hidratar o modelo inteiro e resolver relações por
     * linha transformaria uma página de 25 em dezenas de consultas.
     */
    public function index(ListarLicitacoesRequest $request): JsonResponse
    {
        $consulta = Licitacao::query()
            ->select([
                'id', 'numero_licitacao', 'numero_processo', 'objeto', 'situacao',
                'valor', 'competencia', 'data_abertura', 'data_resultado',
                'codigo_modalidade', 'codigo_ug',
            ])
            ->with(['modalidade:codigo,nome', 'unidadeGestora:codigo_ug,nome,uf,municipio,codigo_orgao', 'unidadeGestora.orgao:codigo_orgao,nome']);

        $this->aplicarFiltros($consulta, $request);

        $ordenar = $request->string('ordenar', 'competencia')->toString();
        $direcao = $request->string('direcao', 'desc')->toString();
        // A coluna já passou pela lista branca do FormRequest, então
        // interpolar aqui é seguro - e é preciso, porque o Eloquent não expõe
        // NULLS LAST. Sem isso, ordenar por valor desc traz as licitações de
        // valor nulo primeiro: o PostgreSQL considera NULL o maior valor.
        $consulta->orderByRaw($this->clausulaDeOrdem($ordenar, $direcao))->orderBy('id');

        $pagina = $consulta->paginate(
            perPage: $request->integer('per_page', 25),
        );

        return response()->json([
            'data' => LicitacaoResource::collection($pagina->items()),
            'meta' => [
                'total' => $pagina->total(),
                'per_page' => $pagina->perPage(),
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
            ],
        ]);
    }

    /** @param Builder<Licitacao> $consulta */
    private function aplicarFiltros(Builder $consulta, ListarLicitacoesRequest $request): void
    {
        $consulta
            ->when($request->filled('codigo_modalidade'), fn (Builder $q) => $q->where('codigo_modalidade', $request->integer('codigo_modalidade')))
            ->when($request->filled('situacao'), fn (Builder $q) => $q->where('situacao', $request->string('situacao')->toString()))
            // Recorte por competencia, não por data_abertura: 72,6% dela é
            // nula, e filtrar por data descartaria a maior parte da base.
            ->when($request->filled('competencia_de'), fn (Builder $q) => $q->where('competencia', '>=', $request->string('competencia_de')->toString()))
            ->when($request->filled('competencia_ate'), fn (Builder $q) => $q->where('competencia', '<=', $request->string('competencia_ate')->toString()))
            // valor nulo fica fora de qualquer faixa: a licitação deserta tem
            // valor nulo, e tratá-lo como zero a colocaria em toda faixa.
            ->when($request->filled('valor_min'), fn (Builder $q) => $q->whereNotNull('valor')->where('valor', '>=', $request->string('valor_min')->toString()))
            ->when($request->filled('valor_max'), fn (Builder $q) => $q->whereNotNull('valor')->where('valor', '<=', $request->string('valor_max')->toString()))
            // ILIKE puro: `unaccent` exigiria extensão no banco, e o dono do
            // esquema é o tcc-jobs. Enquanto isso, a busca é sensível a
            // acento - limitação declarada, não esquecida.
            ->when($request->filled('q'), fn (Builder $q) => $q->where(
                'objeto', 'ILIKE', '%'.$request->string('q')->toString().'%'
            ))
            // Filtros que vivem na unidade gestora entram como subconsulta de
            // códigos, e não por `whereHas`: são 3.484 UGs contra 1,74 milhão
            // de licitações, então resolver a lista primeiro é mais barato que
            // um EXISTS correlacionado por linha.
            ->when(
                $request->filled('uf') || $request->filled('codigo_orgao'),
                fn (Builder $q) => $q->whereIn('codigo_ug', $this->ugsFiltradas($request))
            );
    }

    /**
     * Detalhe com itens e participantes.
     *
     * Medido por `licitacao_id`: 9,7 ms para itens e 8,2 ms para
     * participantes, com índice nas duas tabelas filhas. O eager loading é o
     * que evita uma consulta por filho.
     *
     * Os limites existem porque há licitações com centenas de itens: sem
     * teto, a resposta chegaria a dezenas de MB. Os totais vêm da contagem no
     * banco, então quem consome sabe que foi truncado.
     */
    public function show(int $id): JsonResponse
    {
        $licitacao = Licitacao::query()
            ->with([
                'modalidade:codigo,nome',
                'unidadeGestora:codigo_ug,nome,uf,municipio,codigo_orgao',
                'unidadeGestora.orgao:codigo_orgao,nome',
                'itens' => fn (Relation $q) => $q->limit(self::FILHOS_MAXIMO),
                'participantes' => fn (Relation $q) => $q->limit(self::FILHOS_MAXIMO),
            ])
            ->find($id);

        if ($licitacao === null) {
            return response()->json(['message' => 'Licitação não encontrada.'], 404);
        }

        return response()->json(['data' => new LicitacaoDetalheResource($licitacao)]);
    }

    /**
     * Códigos das unidades gestoras que satisfazem os filtros de localização.
     *
     * uf e municipio pertencem à UG por normalização, e o órgão é alcançado
     * por ela - a licitação referencia a UG, e é a UG que pertence ao órgão.
     */
    private function ugsFiltradas(ListarLicitacoesRequest $request): QueryBuilder
    {
        $consulta = DB::table('unidade_gestora')->select('codigo_ug');

        if ($request->filled('uf')) {
            $consulta->where('uf', $request->string('uf')->upper()->toString());
        }

        if ($request->filled('codigo_orgao')) {
            $consulta->where('codigo_orgao', $request->string('codigo_orgao')->toString());
        }

        return $consulta;
    }

    /**
     * Cláusula de ordenação com NULLS LAST.
     *
     * O Eloquent não expõe NULLS LAST, e sem ela ordenar por valor desc traz
     * primeiro as licitações de valor nulo - o PostgreSQL considera NULL o
     * maior valor. O `match` sobre a lista branca é o que mantém a string
     * literal, sem interpolar entrada do cliente.
     *
     * @return literal-string
     */
    private function clausulaDeOrdem(string $coluna, string $direcao): string
    {
        $asc = $direcao === 'asc';

        return match ($coluna) {
            'valor' => $asc ? 'valor asc nulls last' : 'valor desc nulls last',
            'data_resultado' => $asc ? 'data_resultado asc nulls last' : 'data_resultado desc nulls last',
            'numero_licitacao' => $asc ? 'numero_licitacao asc' : 'numero_licitacao desc',
            default => $asc ? 'competencia asc' : 'competencia desc',
        };
    }
}
