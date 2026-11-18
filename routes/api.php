<?php

use App\Http\Controllers\C2SnapshotController;
use Illuminate\Support\Facades\Route;

// Totais do escritório enviados pela tarefa agendada (conector do C2). Só números agregados.
Route::post('c2/painel', C2SnapshotController::class)->middleware('throttle:30,1')->name('api.c2.snapshot');
