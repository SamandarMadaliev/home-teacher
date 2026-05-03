# Home Teacher — agent / continuity notes

This file is the **tracked** copy of project context for new chats (`.cursor/rules/` may be gitignored locally). Point the AI here or keep this open for handoff.

## What this app is

Local **Laravel** app to watch **course videos from disk** with **saved progress** (SQLite). Assumed **single-user / local** unless you add auth.

## Stack

- Laravel (Blade), SQLite, Vite
- **Plyr** — `resources/js/player.js` (Vite entry `player`)
- CSS: `resources/css/app.css` (e.g. `.courses-grid`, theater mode, Plyr overrides)

## Data model (short)

- **Course** — `title`, `folder_path`; has many **Video** (`sort_order`)
- **VideoProgress** — per-lesson watch state; `progressPercent()` powers bars
- **VideoNote** — `body`, optional `timestamp_seconds` (lesson notes vs time cues)

## Routes worth knowing

- Courses: `courses.index`, `courses.show`, `courses.create`, `courses.store`, `courses.rescan`, `courses.videos.reorder` (POST JSON `{ video_ids: number[] }` — full permutation of that course’s lessons)
- Videos: `videos.show`, `videos.update` (PATCH — rename lesson), `videos.stream`, `videos.progress` (POST JSON)
- Notes: `videos.notes.store`, `videos.notes.destroy`

## Features (do not break casually)

1. **Course index**: cards show **overall progress** — `Course::aggregateProgressPercent()`. List is ordered by **most recent watch activity** (`max(video_progress.updated_at)` per course), then courses with no watch history by title.
2. **Course show** (`/courses/{course}`): **course-wide progress bar** + per-lesson list with **drag handle** to reorder (Sortable.js); **rescan** adds/removes lessons from disk without changing **custom lesson names** you set (scanner only sets titles for newly discovered files); new files append at the end. Current/next lesson use **index order**, not raw `sort_order` gaps.
3. **Video page**: Plyr, theater (`T`), keyboard shortcuts; **Previous / Next lesson** cards **always** shown; **disabled** style on first/last; neighbors by **order index**, not only `sort_order` gaps.
4. **Lesson notes** under the player: general vs **playhead time**; list shows cues that **seek** — `window.__COURSE_PLAYER__.getCurrentTime` / `.seekTo` from `player.js`.
5. **End of video**: optional redirect via `nextUrl` in `__COURSE_PLAYER__`.

## After clone / pull

`composer install`, `php artisan migrate`, `npm ci` or `npm install`, `npm run build` (or `npm run dev`).

Do not commit `.env`.
