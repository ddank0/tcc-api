# tcc-api

API REST do TCC - Sistema Inteligente para Licitações.

PHP 8.3 / Laravel. Consulta as tabelas produzidas por `tcc-jobs` e devolve JSON.
Não executa modelos: previsões e scores chegam prontos, materializados em tabela.

## Requisitos

- PHP 8.3+, Composer
- PostgreSQL 16 com o esquema já migrado por `tcc-jobs`

## Uso

```bash
composer install
cp .env.example .env
php artisan serve
```

Documentação OpenAPI em `/docs`. O `openapi.json` da raiz é versionado e serve
de contrato para o `tcc-frontend`.

## Documentação

`../brain/content/10_Dev/licitacoes-arquitetura.md`.
