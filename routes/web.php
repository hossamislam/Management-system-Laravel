<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestController;
Route::get('/', function () {
    return view('welcome');
    // return "welcome back hossam";
});
Route::get('test',[TestController::class, 'index']);