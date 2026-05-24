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
</nav>
