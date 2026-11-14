<?php

/*
 * Who can create an account (see App\Support\Registration).
 *
 * invite — only through an invite link (default)
 * open   — anyone can sign up
 * closed — nobody can sign up
 *
 * The very first account can always be created (it becomes the admin).
 */
return [
    'mode' => env('REGISTRATION_MODE', 'invite'),

    'invites' => [
        // Limits for people who aren't admins. Admins can also create
        // unlimited, never-expiring invites.
        'max_active_per_user' => 5,
        'max_uses' => 10,
        'max_days' => 30,
    ],
];
