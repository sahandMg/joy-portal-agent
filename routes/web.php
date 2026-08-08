<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'service' => 'Joy Portal Agent',
    'mode' => 'read-only',
    'status' => 'ok',
]));
