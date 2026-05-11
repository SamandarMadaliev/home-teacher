<?php

namespace App\Http\Controllers;

use App\Services\CodeRunner;
use App\Services\LanguageRuntimeProbe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlaygroundController extends Controller
{
    public function show(LanguageRuntimeProbe $probe): View
    {
        $languages = $probe->detectList();

        $available = array_values(array_filter($languages, fn ($l) => $l['available']));
        $default = $available[0]['key'] ?? null;

        return view('playground.show', [
            'languages' => $languages,
            'available' => $available,
            'default' => $default,
            'limits' => [
                'timeout_seconds' => CodeRunner::TIMEOUT_SECONDS,
                'max_code_kb' => (int) (CodeRunner::MAX_CODE_BYTES / 1024),
                'max_output_kb' => (int) (CodeRunner::MAX_OUTPUT_BYTES / 1024),
            ],
        ]);
    }

    public function run(Request $request, CodeRunner $runner, LanguageRuntimeProbe $probe): JsonResponse
    {
        $allowed = array_keys(LanguageRuntimeProbe::LANGUAGES);

        $data = $request->validate([
            'language' => ['required', 'string', 'in:'.implode(',', $allowed)],
            'code' => ['required', 'string', 'max:'.CodeRunner::MAX_CODE_BYTES],
            'stdin' => ['nullable', 'string', 'max:'.CodeRunner::MAX_STDIN_BYTES],
        ]);

        if (! $probe->isAvailable($data['language'])) {
            return response()->json([
                'error' => 'runtime_unavailable',
                'message' => 'That runtime is not installed on this machine.',
            ], 422);
        }

        try {
            $result = $runner->run($data['language'], $data['code'], $data['stdin'] ?? null);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'run_failed',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json($result);
    }

    public function refresh(LanguageRuntimeProbe $probe): RedirectResponse
    {
        $probe->forget();
        $probe->detect(force: true);

        return redirect()->route('playground.show')->with('status', 'Re-checked installed runtimes.');
    }
}
