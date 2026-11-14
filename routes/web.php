<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\FolderPickerController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PlaygroundController;
use App\Http\Controllers\ProblemController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoadmapController;
use App\Http\Controllers\UserAccentController;
use App\Http\Controllers\VideoAttachmentController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\VideoNoteController;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

Route::get('/favicon.ico', function (): Response {
    return response()->file(public_path('favicon.svg'), ['Content-Type' => 'image/svg+xml']);
});

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:30,1');

    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

// Opening an invite link remembers it for sign-up (form or Google).
Route::get('/invite/{token}', [InviteController::class, 'accept'])->middleware('throttle:30,1')->name('invites.accept');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::get('/profile/account', [ProfileController::class, 'account'])->name('profile.account');
    Route::get('/profile/analytics', [ProfileController::class, 'analytics'])->name('profile.analytics');
    Route::get('/profile/notes', [ProfileController::class, 'notes'])->name('profile.notes');
    Route::patch('/profile/accent', [UserAccentController::class, 'update'])->name('profile.accent.update');

    // Courses read folders and files from this computer's disk, so only
    // admins can add them, rescan them or browse folders.
    Route::middleware('admin')->group(function () {
        Route::get('/folder-picker', FolderPickerController::class)->name('folder-picker');
        Route::get('/courses/create', [CourseController::class, 'create'])->name('courses.create');
        Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
        Route::post('/courses/{course}/rescan', [CourseController::class, 'rescan'])->name('courses.rescan');

        Route::get('/profile/users', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::post('/profile/users/{user}/admin', [AdminUserController::class, 'toggleAdmin'])->name('admin.users.toggle-admin');
    });

    Route::get('/profile/invites', [InviteController::class, 'index'])->name('invites.index');
    Route::post('/profile/invites', [InviteController::class, 'store'])->middleware('throttle:20,1')->name('invites.store');
    Route::delete('/profile/invites/{invite}', [InviteController::class, 'destroy'])->name('invites.destroy');
    Route::post('/courses/{course}/archive', [CourseController::class, 'archive'])->name('courses.archive');
    Route::post('/courses/{course}/restore', [CourseController::class, 'restore'])->name('courses.restore');
    Route::post('/courses/{course}/reset-progress', [CourseController::class, 'resetProgress'])->name('courses.reset-progress');
    Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');
    Route::post('/courses/{course}/videos/reorder', [CourseController::class, 'reorderVideos'])->name('courses.videos.reorder');
    Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');
    Route::get('/courses/{course}/preview/stream', [CourseController::class, 'previewStream'])->name('courses.preview.stream');

    Route::get('/playground', [PlaygroundController::class, 'show'])->name('playground.show');
    Route::post('/playground/run', [PlaygroundController::class, 'run'])->name('playground.run');
    Route::post('/playground/refresh', [PlaygroundController::class, 'refresh'])->name('playground.refresh');

    // Static /problems/* routes must come before the resource's {problem} routes.
    Route::get('/problems/random', [ProblemController::class, 'random'])->name('problems.random');
    Route::post('/problems/preview', [ProblemController::class, 'preview'])->name('problems.preview');
    Route::post('/problems/reference-check', [ProblemController::class, 'referenceCheck'])->middleware('throttle:30,1')->name('problems.reference-check');
    Route::resource('problems', ProblemController::class);
    Route::post('/problems/{problem}/run', [ProblemController::class, 'run'])->middleware('throttle:60,1')->name('problems.run');
    Route::post('/problems/{problem}/run-examples', [ProblemController::class, 'runExamples'])->middleware('throttle:60,1')->name('problems.run-examples');
    Route::post('/problems/{problem}/submit', [ProblemController::class, 'submit'])->middleware('throttle:30,1')->name('problems.submit');
    Route::get('/problems/{problem}/submissions/{submission}', [ProblemController::class, 'submission'])->name('problems.submissions.show');

    Route::resource('roadmaps', RoadmapController::class);
    Route::post('/roadmaps/{roadmap}/courses/reorder', [RoadmapController::class, 'reorderCourses'])->name('roadmaps.courses.reorder');
    Route::post('/roadmaps/{roadmap}/courses', [RoadmapController::class, 'attachCourse'])->name('roadmaps.courses.attach');
    Route::delete('/roadmaps/{roadmap}/courses/{course}', [RoadmapController::class, 'detachCourse'])->name('roadmaps.courses.detach');

    Route::get('/videos/{video}', [VideoController::class, 'show'])->name('videos.show');
    Route::patch('/videos/{video}', [VideoController::class, 'update'])->name('videos.update');
    Route::get('/videos/{video}/stream', [VideoController::class, 'stream'])->name('videos.stream');
    Route::post('/videos/{video}/progress', [VideoController::class, 'progress'])->name('videos.progress');
    Route::post('/videos/{video}/mark-complete', [VideoController::class, 'markComplete'])->name('videos.mark-complete');
    Route::post('/videos/{video}/notes/preview', [VideoNoteController::class, 'preview'])->name('videos.notes.preview');
    Route::post('/videos/{video}/notes', [VideoNoteController::class, 'store'])->name('videos.notes.store');
    Route::delete('/videos/{video}/notes/{note}', [VideoNoteController::class, 'destroy'])->name('videos.notes.destroy');
    Route::post('/videos/{video}/attachments', [VideoAttachmentController::class, 'store'])->name('videos.attachments.store');
    Route::get('/videos/{video}/attachments/{attachment}/download', [VideoAttachmentController::class, 'download'])->name('videos.attachments.download');
    Route::delete('/videos/{video}/attachments/{attachment}', [VideoAttachmentController::class, 'destroy'])->name('videos.attachments.destroy');
});
