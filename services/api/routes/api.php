<?php

use App\Http\Controllers\Api\V1\DatasetController;
use App\Http\Controllers\Api\V1\ParcelContextController;
use App\Http\Controllers\Api\V1\ParcelController;
use Illuminate\Support\Facades\Route;

Route::get('/health', static fn () => response()->json(['status' => 'ok']));

Route::put('/datasets/{externalKey}', [DatasetController::class, 'upsert'])
    ->where('externalKey', '[A-Za-z0-9._~-]+');

Route::get('/datasets/{externalKey}/context', ParcelContextController::class)
    ->where('externalKey', '[A-Za-z0-9._~-]+');

Route::put('/datasets/{externalKey}/parcels/{externalId}', [ParcelController::class, 'upsert'])
    ->where('externalKey', '[A-Za-z0-9._~-]+')
    ->where('externalId', '.*');

Route::get('/datasets/{externalKey}/parcels/{externalId}', [ParcelController::class, 'show'])
    ->where('externalKey', '[A-Za-z0-9._~-]+')
    ->where('externalId', '.*');
