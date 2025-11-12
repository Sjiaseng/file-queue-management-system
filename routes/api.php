<?php

use Illuminate\Http\Request;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/uploads', [UploadController::class, 'index']);
Route::post('/uploads', [UploadController::class, 'store']);