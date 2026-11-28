<?php

use App\Http\Controllers\GsmDeblocageApiController;
use Illuminate\Support\Facades\Route;

/* Routes de test : protégez-les (middleware auth / admin) avant de passer en production */
Route::get('/api-test/get-account', [GsmDeblocageApiController::class, 'getAccountInfo'])->name('gsmds.getAccountInfo');
Route::get('/api-test/get-service-list', [GsmDeblocageApiController::class, 'getServiceList'])->name('gsmds.getServiceList');
Route::get('/api-test/place-order', [GsmDeblocageApiController::class, 'placeOrder'])->name('gsmds.placeOrder');
Route::get('/api-test/get-order', [GsmDeblocageApiController::class, 'getOrder'])->name('gsmds.getOrder');
