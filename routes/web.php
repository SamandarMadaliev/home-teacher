<?php

use App\Http\Controllers\CourseController;
use App\Http\Controllers\FolderPickerController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PlaygroundController;
use App\Http\Controllers\RoadmapController;
use App\Http\Controllers\VideoAttachmentController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\VideoNoteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/folder-picker', FolderPickerController::class)->name('folder-picker');

Route::get('/courses/create', [CourseController::class, 'create'])->name('courses.create');
Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
Route::post('/courses/{course}/rescan', [CourseController::class, 'rescan'])->name('courses.rescan');
Route::post('/courses/{course}/archive', [CourseController::class, 'archive'])->name('courses.archive');
Route::post('/courses/{course}/restore', [CourseController::class, 'restore'])->name('courses.restore');
Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');
Route::post('/courses/{course}/videos/reorder', [CourseController::class, 'reorderVideos'])->name('courses.videos.reorder');
Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');

Route::get('/playground', [PlaygroundController::class, 'show'])->name('playground.show');
Route::post('/playground/run', [PlaygroundController::class, 'run'])->name('playground.run');
Route::post('/playground/refresh', [PlaygroundController::class, 'refresh'])->name('playground.refresh');

Route::resource('roadmaps', RoadmapController::class);
Route::post('/roadmaps/{roadmap}/courses/reorder', [RoadmapController::class, 'reorderCourses'])->name('roadmaps.courses.reorder');
Route::post('/roadmaps/{roadmap}/courses', [RoadmapController::class, 'attachCourse'])->name('roadmaps.courses.attach');
Route::delete('/roadmaps/{roadmap}/courses/{course}', [RoadmapController::class, 'detachCourse'])->name('roadmaps.courses.detach');

Route::get('/videos/{video}', [VideoController::class, 'show'])->name('videos.show');
Route::patch('/videos/{video}', [VideoController::class, 'update'])->name('videos.update');
Route::get('/videos/{video}/stream', [VideoController::class, 'stream'])->name('videos.stream');
Route::post('/videos/{video}/progress', [VideoController::class, 'progress'])->name('videos.progress');
Route::post('/videos/{video}/notes', [VideoNoteController::class, 'store'])->name('videos.notes.store');
Route::delete('/videos/{video}/notes/{note}', [VideoNoteController::class, 'destroy'])->name('videos.notes.destroy');
Route::post('/videos/{video}/attachments', [VideoAttachmentController::class, 'store'])->name('videos.attachments.store');
Route::get('/videos/{video}/attachments/{attachment}/download', [VideoAttachmentController::class, 'download'])->name('videos.attachments.download');
Route::delete('/videos/{video}/attachments/{attachment}', [VideoAttachmentController::class, 'destroy'])->name('videos.attachments.destroy');
