<?php

use App\Http\Controllers\Api\V1\DatasetController;
use App\Http\Controllers\Api\V1\ParcelContextController;
use App\Http\Controllers\Api\V1\ParcelController;
use App\Http\Controllers\Api\V1\SourceParcelContextController;
use App\Http\Controllers\Api\V1\SourceParcelController;
use Illuminate\Support\Facades\Route;

Route::get('/health', static fn () => response()->json(['status' => 'ok']));

// Configured authoritative parcel source. Keep the fixed /context route before the catch-all external ID.
Route::get('/parcels/context', SourceParcelContextController::class);
Route::get('/parcels/{externalId}', SourceParcelController::class)
    ->where('externalId', '.*');

// Internal workspace API retained for later transactional editing/session operations.
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
