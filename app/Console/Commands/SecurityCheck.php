<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CodeSandbox;
use App\Support\Registration;
use Illuminate\Console\Command;

/**
 * Checks the settings that matter before other people can reach the site.
 * Exits non-zero when something is unsafe.
 */
class SecurityCheck extends Command
{
    protected $signature = 'app:security-check';

    protected $description = 'Check this installation’s security settings before letting other people in';

    public function handle(CodeSandbox $sandbox): int
    {
        $rows = [];
        $add = function (string $status, string $what, string $detail) use (&$rows) {
            $rows[] = [['ok' => '<fg=green>✓ ok</>', 'warn' => '<fg=yellow>! warn</>', 'fail' => '<fg=red>✗ fix</>'][$status], $what, $detail];
        };

        $production = app()->isProduction();

        $add(config('app.key') ? 'ok' : 'fail', 'APP_KEY', config('app.key') ? 'Set.' : 'Missing — run php artisan key:generate.');

        $add(config('app.debug') ? ($production ? 'fail' : 'warn') : 'ok', 'APP_DEBUG',
            config('app.debug') ? 'On: error pages show code and settings to visitors. Set APP_DEBUG=false before others can reach the site.' : 'Off.');

        $add($production ? 'ok' : 'warn', 'APP_ENV', $production ? 'production.' : app()->environment().' — use APP_ENV=production on a public server.');

        $https = str_starts_with((string) config('app.url'), 'https://');
        $add($https ? 'ok' : 'warn', 'APP_URL', $https ? 'Uses https.' : 'Not https — serve the public site over https.');

        $secure = config('session.secure');
        $add($secure === false ? 'warn' : 'ok', 'Session cookie',
            $secure === false ? 'SESSION_SECURE_COOKIE=false: cookies may travel over http.' : 'Marked Secure on https requests.');

        if (! $sandbox->enabled()) {
            $add('fail', 'Code sandbox', 'CODE_SANDBOX=none: other users’ code runs with full access to this machine.');
        } else {
            $sandbox->forget();
            try {
                $sandbox->assertUsable();
                $add('ok', 'Code sandbox', 'bubblewrap works; user code is isolated.');
            } catch (\RuntimeException) {
                $add('fail', 'Code sandbox', 'bubblewrap is not working — running code is disabled. See the log for the reason.');
            }
        }

        $mode = Registration::mode();
        $add($mode === 'open' ? 'warn' : 'ok', 'Sign-up', match ($mode) {
            'open' => 'Open to anyone (REGISTRATION_MODE=open).',
            'closed' => 'Closed.',
            default => 'Invite only.',
        });

        $admins = User::query()->where('is_admin', true)->count();
        $add($admins > 0 || User::query()->doesntExist() ? 'ok' : 'fail', 'Admins',
            $admins > 0 ? "{$admins} admin(s)." : (User::query()->doesntExist() ? 'No accounts yet — the first one becomes admin.' : 'No admin — run php artisan app:make-admin you@example.com.'));

        $proxies = trim((string) config('app.trusted_proxies'));
        $add($proxies === '*' || $proxies === '**' ? 'warn' : 'ok', 'Trusted proxies',
            $proxies === '*' || $proxies === '**' ? 'Every client is trusted: anyone can fake their IP. Set TRUSTED_PROXIES to your proxy’s address.' : "Only {$proxies}.");

        $roots = trim((string) config('course.browse_roots', ''));
        $add($roots === '' ? 'warn' : 'ok', 'Course folders (admins)',
            $roots === '' ? 'Admins may pick any folder on the computer. Set COURSE_BROWSE_ROOTS to your media folders to narrow it.' : "Limited to {$roots}.");

        $this->table(['', 'Setting', 'Details'], $rows);
        $this->line('Also: serve the site with a real web server (php-fpm + nginx/Caddy, or PHP_CLI_SERVER_WORKERS) — php artisan serve handles one request at a time.');

        $failed = collect($rows)->contains(fn ($r) => str_contains($r[0], 'fix'));
        $failed ? $this->error('Some settings must be fixed before other people can use this site.') : $this->info('No blocking problems found.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
