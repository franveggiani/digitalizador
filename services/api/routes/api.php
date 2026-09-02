<?php

use App\Http\Controllers\Api\V1\DatasetController;
use Illuminate\Support\Facades\Route;

Route::get('/health', static fn () => response()->json(['status' => 'ok']));

Route::put('/datasets/{externalKey}', [DatasetController::class, 'upsert'])
    ->where('externalKey', '[A-Za-z0-9._~-]+');
