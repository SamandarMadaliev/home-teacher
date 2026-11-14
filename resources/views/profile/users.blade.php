@extends('profile._layout')

@section('title', 'Users — '.config('app.name'))

@section('profile_panel')
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Users</h2>
        <p class="mt-1 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-500">
            Everyone with an account on this site. Admins can add courses from this computer’s folders, attach files by path, see every invite and manage this list.
        </p>
    </div>

    @error('users') <p class="mb-4 rounded-lg bg-rose-500/10 p-3 text-sm text-rose-700 dark:text-rose-300">{{ $message }}</p> @enderror

    <div class="card-surface overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200/90 text-xs uppercase tracking-wider text-slate-600 dark:border-slate-800/90 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3 font-semibold">Name</th>
                    <th class="px-4 py-3 font-semibold">Joined</th>
                    <th class="px-4 py-3 font-semibold">Invited by</th>
                    <th class="px-4 py-3 font-semibold">Courses</th>
                    <th class="px-4 py-3 font-semibold"><span class="sr-only">Role</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200/80 dark:divide-slate-800/80">
                @foreach ($users as $user)
                    <tr>
                        <td class="px-4 py-3">
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ $user->name }}</span>
                            @if ($user->id === auth()->id()) <span class="text-xs text-slate-500">(you)</span> @endif
                            <span class="block text-xs text-slate-500">{{ $user->email }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-400">{{ $user->created_at?->toFormattedDateString() }}</td>
                        <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-400">{{ $user->invitedBy?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-400">{{ $user->courses_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <form method="post" action="{{ route('admin.users.toggle-admin', $user) }}" class="inline-flex items-center gap-2"
                                onsubmit="return confirm(@js($user->isAdmin() ? 'Remove admin from '.$user->name.'?' : 'Make '.$user->name.' an admin? Admins can read folders and files on this computer through courses.'))">
                                @csrf
                                @if ($user->isAdmin())
                                    <span class="rounded-full bg-accent/10 px-2.5 py-0.5 text-xs font-semibold text-accent ring-1 ring-accent/25">Admin</span>
                                    <button type="submit" class="btn-ghost text-xs">Remove admin</button>
                                @else
                                    <button type="submit" class="btn-ghost text-xs">Make admin</button>
                                @endif
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
