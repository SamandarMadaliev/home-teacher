@extends('profile._layout')

@section('title', 'Invites — '.config('app.name'))

@php
    $statusStyle = [
        'active' => 'bg-emerald-500/10 text-emerald-700 ring-emerald-500/25 dark:text-emerald-400',
        'used' => 'bg-slate-500/10 text-slate-600 ring-slate-500/25 dark:text-slate-400',
        'expired' => 'bg-amber-500/10 text-amber-700 ring-amber-500/25 dark:text-amber-400',
        'revoked' => 'bg-rose-500/10 text-rose-700 ring-rose-500/25 dark:text-rose-400',
    ];
    $statusText = ['active' => 'Active', 'used' => 'Used up', 'expired' => 'Expired', 'revoked' => 'Revoked'];
    $newId = session('new_invite_id');
@endphp

@section('profile_panel')
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Invite people</h2>
        <p class="mt-1 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-500">
            @if ($mode === 'invite')
                New accounts can only be created through an invite link. Send a link to someone you trust — it works for the sign-up form and for “Continue with Google”.
            @elseif ($mode === 'open')
                Sign-up is currently open to anyone, so invites aren’t required — but links still show who invited whom.
            @else
                Sign-up is closed on this site, so invite links don’t work right now.
            @endif
        </p>
    </div>

    <form method="post" action="{{ route('invites.store') }}" class="card-surface mb-8 p-5 sm:p-6">
        @csrf
        <div class="grid gap-4 sm:grid-cols-[1fr_auto_auto]">
            <div>
                <label for="invite-note" class="mb-1.5 block text-sm font-semibold text-slate-800 dark:text-slate-200">Who is it for? <span class="font-normal text-slate-500">(optional, only you see it)</span></label>
                <input type="text" id="invite-note" name="note" maxlength="120" class="input-field" placeholder="e.g. Ali from study group" value="{{ old('note') }}">
            </div>
            <div>
                <label for="invite-uses" class="mb-1.5 block text-sm font-semibold text-slate-800 dark:text-slate-200">Can be used</label>
                <select id="invite-uses" name="max_uses" class="input-field w-auto">
                    <option value="1">Once</option>
                    <option value="5">5 times</option>
                    <option value="10">10 times</option>
                    @if ($isAdmin)
                        <option value="25">25 times</option>
                        <option value="unlimited">Unlimited</option>
                    @endif
                </select>
            </div>
            <div>
                <label for="invite-expiry" class="mb-1.5 block text-sm font-semibold text-slate-800 dark:text-slate-200">Expires</label>
                <select id="invite-expiry" name="expires_in_days" class="input-field w-auto">
                    <option value="1">In 1 day</option>
                    <option value="7" selected>In 7 days</option>
                    <option value="30">In 30 days</option>
                    @if ($isAdmin)
                        <option value="never">Never</option>
                    @endif
                </select>
            </div>
        </div>
        @error('max_uses') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
        @error('expires_in_days') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
        <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-600 dark:text-slate-500">
                @unless ($isAdmin)
                    {{ $activeCount }} of {{ $limits['max_active_per_user'] }} active invites used.
                @endunless
                People you invite can see only their own courses, problems and progress — never yours.
            </p>
            <button type="submit" class="btn-primary" @disabled($mode === 'closed')>Create invite link</button>
        </div>
    </form>

    <h3 class="mb-3 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $isAdmin ? 'All invites' : 'Your invites' }}</h3>
    @if ($invites->isEmpty())
        <div class="empty-state-soft px-6 py-10 text-center text-sm text-slate-600 dark:text-slate-500">No invites yet.</div>
    @else
        <ul class="space-y-3" role="list">
            @foreach ($invites as $invite)
                @php $status = $invite->status(); @endphp
                <li @class(['card-surface p-4', 'ring-2 ring-accent/50' => $newId === $invite->id])>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $statusStyle[$status] }}">{{ $statusText[$status] }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $invite->note ?? 'Invite #'.$invite->id }}</span>
                        @if ($isAdmin && $invite->user_id !== auth()->id())
                            <span class="text-xs text-slate-500">by {{ $invite->creator?->name }}</span>
                        @endif
                        <span class="ml-auto text-xs text-slate-600 dark:text-slate-500">
                            Used {{ $invite->uses }}{{ $invite->max_uses ? ' / '.$invite->max_uses : '' }}
                            · {{ $invite->expires_at ? ($invite->expires_at->isPast() ? 'expired '.$invite->expires_at->diffForHumans() : 'expires '.$invite->expires_at->diffForHumans()) : 'never expires' }}
                        </span>
                    </div>
                    @if ($status === 'active')
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <input type="text" readonly value="{{ $invite->url() }}" class="input-field min-w-0 flex-1 font-mono text-xs" aria-label="Invite link" onclick="this.select()">
                            <button type="button" class="btn-secondary text-xs" data-copy="{{ $invite->url() }}"
                                onclick="navigator.clipboard?.writeText(this.dataset.copy).then(() => { this.textContent = 'Copied'; setTimeout(() => this.textContent = 'Copy link', 1500) })">Copy link</button>
                            <form method="post" action="{{ route('invites.destroy', $invite) }}" onsubmit="return confirm('Revoke this invite? The link will stop working.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-ghost text-xs text-rose-600">Revoke</button>
                            </form>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($invitees && $invitees->isNotEmpty())
        <h3 class="mt-8 mb-3 text-sm font-semibold text-slate-900 dark:text-slate-100">People who joined through your invites</h3>
        <ul class="card-surface divide-y divide-slate-200/90 dark:divide-slate-800/90" role="list">
            @foreach ($invitees as $person)
                <li class="flex justify-between px-4 py-2.5 text-sm"><span>{{ $person->name }}</span><span class="text-xs text-slate-500">joined {{ $person->created_at->diffForHumans() }}</span></li>
            @endforeach
        </ul>
    @endif
@endsection
