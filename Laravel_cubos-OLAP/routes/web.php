<?php

use App\Http\Controllers\CubosController;

Route::get('/cubos', [CubosController::class, 'index'])->name('cubos.index');

