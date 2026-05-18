@extends('profile._layout')

@section('title', 'Account — '.config('app.name'))

@section('profile_panel')
    <div class="card-surface p-6 sm:p-8">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Account details</h2>
        <dl class="mt-6 space-y-5 text-sm">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-500">Name</dt>
                <dd class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ $user->name }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-500">Email</dt>
                <dd class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ $user->email }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-500">Sign-in</dt>
                <dd class="mt-1 text-slate-700 dark:text-slate-300">
                    @if ($user->google_id)
                        <span class="inline-flex items-center gap-2">
                            <span>Google</span>
                            @if ($user->password)
                                <span class="text-slate-400 dark:text-slate-600">and email / password</span>
                            @endif
                        </span>
                    @else
                        Email and password
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-500">Member since</dt>
                <dd class="mt-1 text-slate-700 dark:text-slate-300">{{ $user->created_at->format('F j, Y') }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <div class="card-surface p-5">
            <p class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $coursesCount }}</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-500">{{ $coursesCount === 1 ? 'Course' : 'Courses' }} in your library</p>
            <a href="{{ route('courses.index') }}" class="mt-3 inline-block text-xs font-semibold text-sky-600 hover:text-sky-800 dark:text-sky-400 dark:hover:text-sky-300">View courses →</a>
        </div>
        <div class="card-surface p-5">
            <p class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $notesCount }}</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-500">{{ $notesCount === 1 ? 'Note' : 'Notes' }} saved</p>
            <a href="{{ route('profile.notes') }}" class="mt-3 inline-block text-xs font-semibold text-sky-600 hover:text-sky-800 dark:text-sky-400 dark:hover:text-sky-300">Browse notes →</a>
        </div>
    </div>
@endsection
