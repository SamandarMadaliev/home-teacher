<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Root directory for video files (absolute path on disk)
    |--------------------------------------------------------------------------
    |
    | Each Video model stores file_path relative to this directory, e.g.
    | "intro.mp4" or "module1/lesson2.mp4".
    |
    */

    'videos_path' => env('COURSE_VIDEOS_PATH', storage_path('app/course-videos')),

    /*
    |--------------------------------------------------------------------------
    | Folder browser roots (Add course)
    |--------------------------------------------------------------------------
    |
    | Comma-separated absolute paths users may browse. If empty, the picker
    | includes the whole machine: "/" on macOS/Linux, all drive letters on
    | Windows, plus Home, /Volumes, etc. Set this only if you want to narrow
    | access (e.g. a single media directory).
    |
    */

    'browse_roots' => env('COURSE_BROWSE_ROOTS', ''),

];
