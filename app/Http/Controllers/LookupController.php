<?php

namespace App\Http\Controllers;

use App\Support\ReferenceCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LookupController extends Controller
{
    public function __invoke(Request $request, string $entity): JsonResponse|RedirectResponse
    {
        abort_unless(in_array($entity, ['fakultas', 'program-studi', 'dosen', 'penulis', 'penerbit', 'jurnal', 'pengindeks', 'tingkat-pengindeksan'], true), 404);
        if (! in_array($entity, ['fakultas', 'program-studi', 'dosen', 'pengindeks'], true)) {
            abort_unless($request->user()?->isActive(), 403);
            abort_if(config('repository.require_verified_email') && ! $request->user()->hasVerifiedEmail(), 403);
        }
        if (str_contains($request->header('Accept', ''), 'text/html') && ! $request->expectsJson()) {
            if ($request->user()?->isActive() && Gate::allows('manage-reference')) {
                return redirect()->route('admin.reference.index', $entity);
            }

            return $entity === 'dosen'
                ? redirect()->route('repository.lecturers')
                : redirect()->route('repository.search');
        }
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1', 'max:1000'], 'available_for' => ['nullable', 'integer', 'min:0']]);
        $definition = ReferenceCatalog::get($entity);
        $label = $definition['title'];
        $query = $definition['model']::query()->select(['id', $label])->where('aktif', true);
        if ($entity === 'program-studi') {
            $query->whereHas('fakultas', fn ($faculty) => $faculty->where('aktif', true));
        }
        if ($entity === 'penulis' && isset($data['available_for'])) {
            Gate::authorize('manage-users');
            $query->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $data['available_for']));
        }
        if (filled($data['q'] ?? null)) {
            $term = trim($data['q']);
            $pattern = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
            $query->where(function ($query) use ($label, $pattern, $term): void {
                $query->whereRaw($label." LIKE ? ESCAPE '!'", [$pattern]);
                if (ctype_digit($term)) {
                    $query->orWhere('id', (int) $term);
                }
            });
        }
        $results = $query->orderBy($label)->orderBy('id')->simplePaginate(25);

        return response()->json([
            'data' => $results->map(fn ($row) => ['id' => $row->id, 'label' => $row->$label.' · #'.$row->id]),
            'has_more' => $results->hasMorePages(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
