# Home Teacher

Home Teacher is a local Laravel app for watching course videos stored on disk while tracking lesson progress, notes, and resources. It is designed for **single-user, local usage** — there is no multi-user auth.

## Features

- Add courses by pointing to folders on your machine.
- Auto-scan and list video lessons per course (rescan to pick up new files without losing custom names / order).
- Watch lessons with a Plyr-based player (keyboard shortcuts, theater mode, autoplay next).
- Per-lesson and aggregate course progress, with a resume / start / replay button.
- Drag-to-reorder lessons inside a course.
- Lesson notes with optional timestamp cues that **seek** back to that moment.
- **Markdown formatting** in notes (toolbar + keyboard shortcuts; bold, italic, code, lists, quotes, links).
- **Lesson resources** — attach local files (by path, no upload) or external links to any lesson.
- **Coding playground** — run JavaScript / Python / PHP / Go snippets locally, with a CodeMirror editor, sandboxed temp dir, timeout, and output caps. Only shows the runtimes installed on your machine.

## Tech Stack

- **PHP** 8.3+ · **Laravel** 13 · Blade templates
- **SQLite** (default; PostgreSQL config is also stubbed in `.env.example`)
- **Vite** 8 (Tailwind v4)
- **Plyr** (`resources/js/player.js`) · **Sortable.js** · **CodeMirror 6** (playground only) · **CommonMark** (note rendering)

---

## Prerequisites

You need these installed before you start:

| Tool | Min version | Notes |
|---|---|---|
| PHP | **8.3** | `php -v` to check |
| Composer | 2.x | [getcomposer.org](https://getcomposer.org/) |
| Node.js | **20+** | `node -v` to check |
| npm | 10+ | ships with Node |
| SQLite | 3.x | usually preinstalled on macOS / Linux |

### Install on macOS (Homebrew)

```bash
brew install php composer node sqlite
```

### Install on Debian / Ubuntu

```bash
sudo apt update
sudo apt install php-cli php-mbstring php-xml php-sqlite3 php-curl php-zip \
                 composer nodejs npm sqlite3 unzip
```

### Install on Windows

The easiest path is **[Laravel Herd](https://herd.laravel.com)** (bundles PHP + Composer) plus **[Node.js LTS](https://nodejs.org/)** and **[Git](https://git-scm.com/)**.

### Optional — playground language runtimes

The `/playground` page only shows languages that are detected on your machine. Install any subset you want:

```bash
# macOS (Homebrew)
brew install node python php go

# Debian / Ubuntu
sudo apt install nodejs python3 php-cli golang-go
```

After installing more runtimes, hit the **Re-check** button in the playground header (or `POST /playground/refresh`) to clear the 1-hour detection cache.

---

## Quick start (one command)

After cloning, the `composer setup` script runs the full install + migrate + asset build chain:

```bash
git clone <your-fork-url> home-teacher
cd home-teacher
composer run setup
php artisan serve
```

Then open <http://127.0.0.1:8000>.

`composer run setup` is just shorthand for: `composer install` → copy `.env.example` to `.env` → `php artisan key:generate` → `php artisan migrate --force` → `npm install` → `npm run build`.

---

## Manual setup (step by step)

Use this if you want to understand every step or if `composer run setup` failed partway through.

1. **Clone the repository**

    ```bash
    git clone <your-fork-url> home-teacher
    cd home-teacher
    ```

2. **Install PHP dependencies**

    ```bash
    composer install
    ```

3. **Create the environment file and app key**

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

4. **Create the SQLite database file and run migrations**

    The repo doesn't ship a `database/database.sqlite` — create an empty one, then migrate:

    ```bash
    touch database/database.sqlite
    php artisan migrate
    ```

    > Prefer PostgreSQL? Uncomment the `DB_CONNECTION=pgsql` block in `.env` and adjust credentials. Then `php artisan migrate` against your Postgres instance.

5. **Install frontend dependencies and build assets**

    ```bash
    npm install
    npm run build
    ```

6. **Start the app**

    ```bash
    php artisan serve
    ```

    The app is now at <http://127.0.0.1:8000>.

---

## Development mode

For active development, run everything (HTTP server, queue worker, log tail, Vite dev server) in one terminal:

```bash
composer run dev
```

Or, individually:

```bash
# Terminal 1 — Laravel
php artisan serve

# Terminal 2 — Vite dev server (HMR for CSS / JS)
npm run dev
```

Run the test suite:

```bash
php artisan test
# or:
composer run test
```

Lint PHP:

```bash
./vendor/bin/pint
```

---

## Configuration

All configuration lives in `.env`. Keys worth knowing:

| Key | Purpose |
|---|---|
| `APP_NAME` | Brand name shown in the navbar and `<title>`. Default `"Home Teacher"`. |
| `APP_URL` | Used when generating absolute URLs. |
| `DB_CONNECTION` | `sqlite` (default) or `pgsql`. |
| `COURSE_VIDEOS_PATH` | Optional absolute folder where your course videos live. Course folder paths can be saved **relative** to this, otherwise absolute paths are fine too. |
| `COURSE_BROWSE_ROOTS` | Comma-separated absolute paths the in-app folder picker is allowed to browse. Leave empty to allow browsing the whole machine. Example: `COURSE_BROWSE_ROOTS=/Users/you,/Volumes`. |

Do not commit `.env` — it stays local.

---

## First-time usage

1. Open <http://127.0.0.1:8000>.
2. Click **Add course**, give it a title, and either paste an absolute folder path or click **Browse folders** to pick one. The folder should contain video files (`.mp4`, `.mkv`, `.webm`, …).
3. Save — the course list now shows it with auto-detected lessons.
4. Open a course → click any lesson to start watching. Progress is saved automatically while you watch.
5. Use **Resources** under the player to attach related files (paste a file path; the file stays where it is on disk) or external links.
6. Use **Notes** to capture takeaways. Use `⌘/Ctrl+B`, `⌘/Ctrl+I`, `⌘/Ctrl+E`, `⌘/Ctrl+K` inside the textarea for bold / italic / inline code / link.
7. Optional — visit `/playground` to run code in any installed language without leaving the browser.

---

## Updating after `git pull`

```bash
composer install
php artisan migrate
npm install
npm run build
```

If you see stale assets or a blank UI after pulling, clear caches:

```bash
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

---

## Important routes

- `courses.index` — list all courses
- `courses.show` — show a course and its lessons
- `courses.create` / `courses.store` — add a new course
- `courses.rescan` — rescan a course folder for new lessons
- `courses.videos.reorder` — save custom lesson order
- `videos.show` — lesson watch page
- `videos.update` — rename a lesson
- `videos.stream` — video streaming endpoint
- `videos.progress` — save watch progress
- `videos.notes.store` / `videos.notes.destroy` — lesson notes (markdown)
- `videos.attachments.store` / `videos.attachments.download` / `videos.attachments.destroy` — lesson resources
- `playground.show` / `playground.run` / `playground.refresh` — code playground

---

## Troubleshooting

**`No application encryption key has been specified.`**
You skipped step 3. Run:
```bash
php artisan key:generate
```

**`SQLSTATE[HY000] [14] unable to open database file`**
The SQLite file doesn't exist or isn't writable. Run:
```bash
touch database/database.sqlite
chmod 664 database/database.sqlite
```

**Old / missing CSS after a pull**
The compiled assets in `public/build/` are out of sync. Rebuild:
```bash
npm install
npm run build
```
For dev work prefer `npm run dev` so changes hot-reload.

**Playground says "No language runtimes detected"**
Install at least one of `node`, `python3`, `php`, `go` (see [Optional — playground language runtimes](#optional--playground-language-runtimes) above), then click **Re-check** on the playground page.

**Playground Go run errors with `GOCACHE is not defined`**
This is handled by the app (each run gets a private `HOME` / `GOCACHE` inside its temp dir). If you still see it, make sure `storage/app/` is writable:
```bash
chmod -R u+w storage/
```

**`Class "League\CommonMark\..." not found`** after a pull
Composer dependencies are out of date. Run:
```bash
composer install
```

---

## Notes

- This app is intended for **local, single-user** use. There is no authentication.
- File attachments are **referenced**, not uploaded — files live where they already are on disk and are read in place. Deleting an attachment from a lesson does **not** delete the file.
- The playground runs untrusted code on your own machine inside a temporary folder with an 8-second wall-clock timeout and a 256 KB output cap per stream. It is not a sandbox in the kernel-isolation sense; only enable it on machines you trust to execute the code you write.
- Do not commit `.env` files or sensitive local paths.
