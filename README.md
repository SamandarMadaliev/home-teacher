# Home Teacher

Home Teacher is a local Laravel app for watching course videos stored on disk while tracking lesson progress and notes.

It is designed for single-user, local usage (no multi-user auth assumptions).

## Features

- Add courses by pointing to folders on your machine.
- Automatically scan and list lessons (video files) per course.
- Watch lessons with a Plyr-based player and keyboard shortcuts.
- Save playback progress for each lesson.
- View per-lesson and aggregate course progress.
- Reorder lessons in a course.
- Keep lesson notes with optional timestamps (seek back to saved cues).
- Navigate quickly with previous/next lesson cards and optional autoplay to next lesson.

## Tech Stack

- Laravel (Blade templates)
- SQLite
- Vite
- Plyr (`resources/js/player.js`)

## Requirements

- PHP and Composer
- Node.js and npm
- SQLite

## Local Setup

1. Install PHP dependencies:

   ```bash
   composer install
   ```

2. Create environment file and app key (if not already present):

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. Run database migrations:

   ```bash
   php artisan migrate
   ```

4. Install frontend dependencies and build assets:

   ```bash
   npm install
   npm run build
   ```

5. Start the app:

   ```bash
   php artisan serve
   ```

For active frontend development, use:

```bash
npm run dev
```

## Main Application Flow

1. Open the course list.
2. Add a course and set the folder path containing video files.
3. Open a course to view lessons and progress.
4. Play lessons; progress is saved automatically.
5. Add general notes or timestamped cue notes while watching.

## Important Routes

- `courses.index` - list all courses
- `courses.show` - show a course and its lessons
- `courses.create` / `courses.store` - add a new course
- `courses.rescan` - rescan a course folder for lessons
- `courses.videos.reorder` - save custom lesson order
- `videos.show` - lesson watch page
- `videos.stream` - video streaming endpoint
- `videos.progress` - save watch progress
- `videos.notes.store` / `videos.notes.destroy` - lesson notes

## Notes

- This app is intended for local use.
- Do not commit `.env` files or sensitive local paths.
