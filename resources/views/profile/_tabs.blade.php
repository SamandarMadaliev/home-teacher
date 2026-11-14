@php
    $active = $activeTab ?? 'account';
@endphp
<nav class="profile-tabs" aria-label="Profile sections">
    <a
        href="{{ route('profile.account') }}"
        @class(['profile-tab', 'profile-tab--active' => $active === 'account'])
        @if ($active === 'account') aria-current="page" @endif
    >
        Account
    </a>
    <a
        href="{{ route('profile.analytics') }}"
        @class(['profile-tab', 'profile-tab--active' => $active === 'analytics'])
        @if ($active === 'analytics') aria-current="page" @endif
    >
        Progress
    </a>
    <a
        href="{{ route('profile.notes') }}"
        @class(['profile-tab', 'profile-tab--active' => $active === 'notes'])
        @if ($active === 'notes') aria-current="page" @endif
    >
        Notes
    </a>
    <a
        href="{{ route('invites.index') }}"
        @class(['profile-tab', 'profile-tab--active' => $active === 'invites'])
        @if ($active === 'invites') aria-current="page" @endif
    >
        Invites
    </a>
    @if (auth()->user()->isAdmin())
        <a
            href="{{ route('admin.users.index') }}"
            @class(['profile-tab', 'profile-tab--active' => $active === 'users'])
            @if ($active === 'users') aria-current="page" @endif
        >
            Users
        </a>
    @endif
</nav>
