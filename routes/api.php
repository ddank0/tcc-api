<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        $banco = 'ok';
    } catch (Throwable $erro) {
        $banco = 'erro';
    }

    return response()->json([
        'status' => 'ok',
        'database' => $banco,
    ]);
});
