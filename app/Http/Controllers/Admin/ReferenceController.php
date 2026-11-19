<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReferenceRequest;
use App\Models\AuditLog;
use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\Jurnal;
use App\Models\Penerbit;
use App\Models\Pengindeks;
use App\Models\Penulis;
use App\Models\ProgramStudi;
use App\Models\TingkatPengindeksan;
use App\Support\OptimisticLock;
use App\Support\ReferenceCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReferenceController extends Controller
{
    public function index(Request $request, string $entity): View
    {
        Gate::authorize('manage-reference');
        $definition = ReferenceCatalog::get($entity);
        $q = $request->validate(['q' => ['nullable', 'string', 'max:150']])['q'] ?? '';
        $records = $definition['model']::when($q, fn ($query) => $query->where($definition['title'], 'like', '%'.$q.'%'))->orderBy($definition['title'])->paginate(20)->withQueryString();

        return view('admin.reference.index', compact('entity', 'definition', 'records'));
    }

    /** Status endpoint for master reference records; never resolves through WorkType. */
    public function status(string $entity, int $id): JsonResponse
    {
        Gate::authorize('manage-reference');
        $definition = ReferenceCatalog::get($entity);
        $record = $definition['model']::query()->findOrFail($id);

        return response()->json([
            'id' => $record->getKey(),
            'version' => (int) ($record->lock_version ?? 0),
            'active' => $record->aktif === null ? null : (bool) $record->aktif,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function create(string $entity): View
    {
        return $this->form($entity);
    }

    public function edit(string $entity, int $id): View
    {
        return $this->form($entity, $id);
    }

    private function form(string $entity, ?int $id = null): View
    {
        Gate::authorize('manage-reference');
        $definition = ReferenceCatalog::get($entity);
        $record = $id ? $definition['model']::findOrFail($id) : new $definition['model'];

        return view('admin.reference.form', compact('entity', 'definition', 'record'));
    }

    public function store(ReferenceRequest $request, string $entity): RedirectResponse
    {
        return $this->save($request, $entity);
    }

    public function update(ReferenceRequest $request, string $entity, int $id): RedirectResponse
    {
        return $this->save($request, $entity, $id);
    }

    private function save(ReferenceRequest $request, string $entity, ?int $id = null): RedirectResponse
    {
        $definition = ReferenceCatalog::get($entity);
        DB::transaction(function () use ($request, $definition, $id, $entity) {
            $record = $id ? $definition['model']::lockForUpdate()->findOrFail($id) : new $definition['model'];
            Gate::forUser($request->user()->fresh())->authorize('manage-reference');
            if ($id) {
                OptimisticLock::check($record, (int) $request->validated('lock_version'));
            }
            $old = $record->only(array_keys($definition['fields']));
            $data = $request->validated();
            if ($entity === 'penulis') {
                $data['nama_normalisasi'] = mb_strtolower(preg_replace('/\s+/u', ' ', trim($data['nama'])));
            }
            unset($data['lock_version']);
            $record->fill($data);
            $record->lock_version = (int) $record->lock_version + 1;
            $record->save();
            AuditLog::record($record, $request->user(), $id ? 'reference_updated' : 'reference_created', $old, $data);
        }, 3);

        return redirect()->route('admin.reference.index', $entity)->with('success', 'Data berhasil disimpan.');
    }

    public function destroy(Request $request, string $entity, int $id): RedirectResponse
    {
        Gate::authorize('manage-reference');
        $definition = ReferenceCatalog::get($entity);
        $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);
        try {
            DB::transaction(function () use ($request, $definition, $id) {
                $record = $definition['model']::lockForUpdate()->findOrFail($id);
                Gate::forUser($request->user()->fresh())->authorize('manage-reference');
                OptimisticLock::check($record, (int) $request->input('lock_version'));
                $relations = match ($record::class) {
                    Fakultas::class => ['programStudi'], ProgramStudi::class => ['dosen'],
                    Dosen::class => ['penulis'], Penulis::class => ['publikasi', 'buku', 'kekayaanIntelektual'],
                    Penerbit::class => ['jurnal', 'buku'], Jurnal::class => ['publikasi'],
                    Pengindeks::class,TingkatPengindeksan::class => ['publikasi'], default => [],
                };
                foreach ($relations as $relation) {
                    if ($record->$relation()->exists()) {
                        throw ValidationException::withMessages(['delete' => 'Data masih digunakan. Nonaktifkan data bila tidak digunakan lagi.']);
                    }
                }
                AuditLog::record($record, $request->user(), 'reference_deleted', $record->only(array_keys($definition['fields'])));
                $record->delete();
            }, 3);
        } catch (QueryException $e) {
            if (str_contains(strtolower($e->getMessage()), 'foreign key')) {
                return back()->withErrors(['delete' => 'Data masih digunakan. Nonaktifkan data bila tidak digunakan lagi.']);
            }
            throw $e;
        }

        return back()->with('success', 'Data berhasil dihapus.');
    }
}
