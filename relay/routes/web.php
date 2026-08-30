<?php

use Illuminate\Support\Facades\Route;

// Deliberately no signup, login, or admin UI in Phase 1: accounts are created
// only by `relay:approve`, and every credential is minted from artisan.
Route::get('/', fn () => response()->json([
    'service' => 'The Missing OmniFocus MCP relay',
    'phase' => 1,
]));
