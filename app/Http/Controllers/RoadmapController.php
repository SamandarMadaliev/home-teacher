<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Roadmap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoadmapController extends Controller
{
    public function index(): View
    {
        $roadmaps = Roadmap::query()
            ->withCount('courses')
            ->orderByDesc('updated_at')
            ->orderBy('title')
            ->get();

        return view('roadmaps.index', compact('roadmaps'));
    }

    public function create(): View
    {
        return view('roadmaps.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
        ]);

        $roadmap = Roadmap::create($validated);

        return redirect()->route('roadmaps.show', $roadmap)->with('status', 'Roadmap created. Add courses and drag them into the order you want.');
    }

    public function show(Roadmap $roadmap): View
    {
        $roadmap->loadCount('courses');
        $courses = $roadmap->courses()
            ->withCount('videos')
            ->with(['videos.progress'])
            ->orderByPivot('sort_order')
            ->get();

        $currentCourse = $this->resolveCurrentCourse($courses);

        $allComplete = $courses->isNotEmpty()
            && $courses->every(fn (Course $c) => $c->aggregateProgressPercent() >= 100);

        $attachedIds = $courses->pluck('id')->all();
        $availableCourses = Course::query()
            ->when($attachedIds !== [], fn ($q) => $q->whereNotIn('id', $attachedIds))
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('roadmaps.show', [
            'roadmap' => $roadmap,
            'courses' => $courses,
            'currentCourse' => $currentCourse,
            'allComplete' => $allComplete,
            'availableCourses' => $availableCourses,
            'libraryHasCourses' => Course::query()->exists(),
        ]);
    }

    public function edit(Roadmap $roadmap): View
    {
        return view('roadmaps.edit', compact('roadmap'));
    }

    public function update(Request $request, Roadmap $roadmap): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
        ]);

        $roadmap->update($validated);

        return redirect()->route('roadmaps.show', $roadmap)->with('status', 'Roadmap updated.');
    }

    public function destroy(Roadmap $roadmap): RedirectResponse
    {
        $roadmap->delete();

        return redirect()->route('roadmaps.index')->with('status', 'Roadmap deleted.');
    }

    public function reorderCourses(Request $request, Roadmap $roadmap): JsonResponse
    {
        $data = $request->validate([
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['integer', 'distinct'],
        ]);

        // Normalize to int (SQLite may return string IDs from pluck; JSON sends ints).
        $expectedIds = collect($roadmap->courses()->pluck('courses.id')->all())
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $incomingIds = collect($data['course_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($expectedIds !== $incomingIds) {
            abort(422, 'Course list must include every course in this roadmap exactly once.');
        }

        $orderedIds = array_values(array_map(static fn ($id) => (int) $id, $data['course_ids']));

        DB::transaction(function () use ($roadmap, $orderedIds): void {
            foreach ($orderedIds as $i => $courseId) {
                $roadmap->courses()->updateExistingPivot($courseId, [
                    'sort_order' => 1_000_000 + $i,
                ]);
            }
            foreach ($orderedIds as $i => $courseId) {
                $roadmap->courses()->updateExistingPivot($courseId, [
                    'sort_order' => $i + 1,
                ]);
            }
        });

        return response()->json(['ok' => true]);
    }

    public function attachCourse(Request $request, Roadmap $roadmap): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
        ]);

        if ($roadmap->courses()->where('courses.id', $data['course_id'])->exists()) {
            return back()->withErrors(['course_id' => 'That course is already on this roadmap.']);
        }

        $max = (int) (DB::table('roadmap_course')
            ->where('roadmap_id', $roadmap->id)
            ->max('sort_order') ?? 0);

        $roadmap->courses()->attach($data['course_id'], [
            'sort_order' => $max + 1,
        ]);

        return back()->with('status', 'Course added to the roadmap.');
    }

    public function detachCourse(Roadmap $roadmap, Course $course): RedirectResponse
    {
        if (! $roadmap->courses()->where('courses.id', $course->id)->exists()) {
            abort(404);
        }

        $roadmap->courses()->detach($course->id);

        $remaining = $roadmap->courses()->orderByPivot('sort_order')->pluck('courses.id')->all();

        DB::transaction(function () use ($roadmap, $remaining): void {
            foreach ($remaining as $i => $courseId) {
                $roadmap->courses()->updateExistingPivot($courseId, [
                    'sort_order' => 1_000_000 + $i,
                ]);
            }
            foreach ($remaining as $i => $courseId) {
                $roadmap->courses()->updateExistingPivot($courseId, [
                    'sort_order' => $i + 1,
                ]);
            }
        });

        return back()->with('status', 'Course removed from the roadmap.');
    }

    /**
     * First course in roadmap order that is not fully complete (0–100 aggregate).
     *
     * @param  Collection<int, Course>  $courses
     */
    private function resolveCurrentCourse($courses): ?Course
    {
        foreach ($courses as $course) {
            if ($course->aggregateProgressPercent() < 100) {
                return $course;
            }
        }

        return $courses->last();
    }
}
