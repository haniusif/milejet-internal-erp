<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NewsPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Company news / announcements. HR (hr.view_all) manages posts; everyone reads
 * the published feed. Laravel-only — no Odoo dependency.
 */
class NewsController extends Controller
{
    private const CATEGORIES = ['announcement', 'policy', 'event', 'general'];

    /** Published feed (mobile + employee) — bare list in the app's NewsPost shape. */
    public function feed(): JsonResponse
    {
        $rows = NewsPost::with('author')->where('published', true)
            ->orderByDesc('pinned')->orderByDesc('published_at')->orderByDesc('id')
            ->limit(100)->get()->map(fn ($n) => $this->shape($n));
        return response()->json($rows);
    }

    /** Admin list — staff see everything (incl. drafts); others get published only. */
    public function index(Request $request): JsonResponse
    {
        $q = NewsPost::with('author');
        if (!$request->user()->can('hr.view_all')) {
            $q->where('published', true);
        }
        $rows = $q->orderByDesc('pinned')->orderByDesc('id')->limit(200)->get()
            ->map(fn ($n) => $this->shape($n) + ['published' => $n->published]);
        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('hr.view_all'), 403);
        $data = $this->validatePayload($request);
        $published = $data['published'] ?? true;
        $post = NewsPost::create($data + [
            'author_id' => $request->user()->id,
            'published' => $published,
            'published_at' => $published ? now() : null,
        ]);
        return response()->json(['data' => $this->shape($post->load('author')) + ['published' => $post->published]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('hr.view_all'), 403);
        $post = NewsPost::findOrFail($id);
        $data = $this->validatePayload($request);
        // stamp published_at the first time it goes live
        if (($data['published'] ?? $post->published) && !$post->published_at) {
            $data['published_at'] = now();
        }
        $post->update($data);
        return response()->json(['data' => $this->shape($post->fresh()->load('author')) + ['published' => $post->published]]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('hr.view_all'), 403);
        NewsPost::findOrFail($id)->delete();
        return response()->json(['message' => 'deleted']);
    }

    protected function validatePayload(Request $request): array
    {
        return $request->validate([
            'title'     => 'required|string|max:255',
            'body'      => 'nullable|string|max:10000',
            'category'  => ['nullable', Rule::in(self::CATEGORIES)],
            'pinned'    => 'boolean',
            'published' => 'boolean',
        ]);
    }

    protected function shape(NewsPost $n): array
    {
        return [
            'id'           => $n->id,
            'title'        => $n->title,
            'body'         => $n->body ?? '',
            'category'     => $n->category,
            'author'       => $n->author?->name ?? '—',
            'pinned'       => $n->pinned,
            'published_at' => ($n->published_at ?? $n->created_at)?->toIso8601String(),
        ];
    }
}
