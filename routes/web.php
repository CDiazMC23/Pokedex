<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

use App\Http\Controllers\PokemonController;
Route::get('/', [PokemonController::class, 'index']);