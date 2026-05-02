<?php

use App\Http\Controllers\CourseController;
use App\Http\Controllers\FolderPickerController;
use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('courses.index');
});

Route::get('/folder-picker', FolderPickerController::class)->name('folder-picker');

Route::get('/courses/create', [CourseController::class, 'create'])->name('courses.create');
Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
Route::post('/courses/{course}/rescan', [CourseController::class, 'rescan'])->name('courses.rescan');
Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');

Route::get('/videos/{video}', [VideoController::class, 'show'])->name('videos.show');
Route::get('/videos/{video}/stream', [VideoController::class, 'stream'])->name('videos.stream');
Route::post('/videos/{video}/progress', [VideoController::class, 'progress'])->name('videos.progress');
