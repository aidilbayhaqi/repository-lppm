<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkRequest;
use App\Models\AuditLog;
use App\Models\DocumentVersion;
use App\Models\Fakultas;
use App\Models\Jurnal;
use App\Models\Penerbit;
use App\Models\Pengindeks;
use App\Models\Penulis;
use App\Models\ProgramStudi;
use App\Models\PublishAttempt;
use App\Models\TingkatPengindeksan;
use App\Models\VerificationHistory;
use App\Services\SearchService;
use App\Services\WorkSubmissionService;
use App\Support\LookupOptions;
use App\Support\PublicationReadiness;
use App\Support\WorkFilters;
use App\Support\WorkType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WorkController extends Controller
{
    public function index(Request $request, string $type, SearchService $search): View
    {
        Gate::authorize('viewAny', WorkType::model($type));
        $filters = $request->validate(WorkFilters::rules());
        $query = $search->query($type, $filters, $request->user())->with($type === 'hki' ? 'pencipta' : 'penulis');
        match ($filters['sort'] ?? 'updated') {
            'newest' => $query->orderByDesc(WorkType::year($type)),
            'oldest' => $query->orderBy(WorkType::year($type)),
            'title' => $query->orderBy('judul'),
            default => $query->latest('updated_at'),
        };
        $records = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('admin.works.index', compact('type', 'records') + WorkFilters::options());
    }

    public function create(string $type): View
    {
        Gate::authorize('create', WorkType::model($type));

        return $this->form($type);
    }

    public function edit(string $type, int $id): View
    {
        Gate::authorize('update', WorkType::model($type)::findOrFail($id));

        return $this->form($type, $id);
    }

    private function form(string $type, ?int $id = null): View
    {
        $class = WorkType::model($type);
        $work = $id ? $class::findOrFail($id) : new $class;

        return view('admin.works.form', [
            'faculties' => LookupOptions::initial(Fakultas::active(), 'nama_fakultas', [old('fakultas_id', $work->fakultas_id)]),
            'programs' => LookupOptions::initial(ProgramStudi::active()->whereHas('fakultas', fn ($query) => $query->where('aktif', true))->with('fakultas'), 'nama', [old('program_studi_id', $work->program_studi_id)]),
            'type' => $type, 'work' => $work, 'authors' => LookupOptions::initial(Penulis::active()->select(['id', 'nama', 'jenis_penulis']), 'nama', array_merge(collect(old('authors', []))->pluck('id')->all(), $work->exists ? $work->{WorkType::authors($work)}->pluck('id')->all() : [auth()->user()->penulis?->id])),
            'journals' => LookupOptions::initial(Jurnal::query()->select(['id', 'nama_jurnal']), 'nama_jurnal', [old('jurnal_id', $work->jurnal_id)]), 'publishers' => LookupOptions::initial(Penerbit::query()->select(['id', 'nama']), 'nama', [old('penerbit_id', $work->penerbit_id)]),
            'indexers' => LookupOptions::initial(Pengindeks::query(), 'nama', collect(old('indexers', $type === 'publikasi' && $work->exists ? $work->pengindeks->toArray() : []))->pluck('id')->all()), 'levels' => LookupOptions::initial(TingkatPengindeksan::query(), 'nama', collect(old('indexers', $type === 'publikasi' && $work->exists ? $work->pengindeks->map(fn ($row) => ['tingkat_pengindeksan_id' => $row->pivot->tingkat_pengindeksan_id])->all() : []))->pluck('tingkat_pengindeksan_id')->all()),
        ]);
    }

    public function store(WorkRequest $request, string $type, WorkSubmissionService $service): RedirectResponse
    {
        $work = $service->save($type, $request->validated(), $request->user());

        return redirect()->route('admin.works.show', [$type, $work->id])->with('success', 'Karya disimpan sebagai draf.');
    }

    public function update(WorkRequest $request, string $type, int $id, WorkSubmissionService $service): RedirectResponse
    {
        $work = $service->save($type, $request->validated(), $request->user(), $id);

        return redirect()->route('admin.works.show', [$type, $work->id])->with('success', 'Perubahan berhasil disimpan.');
    }

    public function status(Request $request, string $type, int $id): JsonResponse|RedirectResponse
    {
        $work = WorkType::model($type)::findOrFail($id);
        Gate::authorize('view', $work);

        if (str_contains($request->header('Accept', ''), 'text/html') && ! $request->expectsJson()) {
            return redirect()->route('admin.works.show', [$type, $id]);
        }

        return response()->json([
            'status' => WorkType::status($work),
            'version' => $work->lock_version,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(string $type, int $id): View
    {
        $work = WorkType::model($type)::findOrFail($id);
        Gate::authorize('view', $work);
        $work->load([WorkType::authors($work), 'dokumen']);
        $readiness = $type === 'publikasi'
        ? PublicationReadiness::evaluate($work)
        : null;
        $histories = AuditLog::where('subject_type', $work->getMorphClass())->where('subject_id', $id)->with('user')->latest('id')->paginate(5, ['*'], 'history_page')->withQueryString()->fragment('activity-history');
        $attempts = $type === 'publikasi' ? PublishAttempt::where('publikasi_id', $id)->latest('id')->paginate(5, ['*'], 'attempt_page')->withQueryString()->fragment('publishing-history') : collect();
        $verifications = $type === 'publikasi' ? VerificationHistory::where('publikasi_id', $id)->with('verifier:id,nama')->latest('id')->paginate(5, ['*'], 'verification_page')->withQueryString()->fragment('verification-history') : collect();
        $versionPage = DocumentVersion::whereIn('dokumen_id', $work->dokumen->pluck('id'))->latest('id')->paginate(20, ['*'], 'version_page');
        $versions = $versionPage->getCollection()->groupBy('dokumen_id');

        return view('admin.works.show', compact(
            'type',
            'work',
            'histories',
            'attempts',
            'verifications',
            'versions',
            'versionPage',
            'readiness'
        ));
    }
}
