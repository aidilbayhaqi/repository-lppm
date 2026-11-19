<?php

namespace App\Http\Controllers;

use App\Models\Buku;
use App\Models\Dosen;
use App\Models\Jurnal;
use App\Models\KekayaanIntelektual;
use App\Models\Penulis;
use App\Models\Publikasi;
use App\Services\SearchService;
use App\Support\WorkFilters;
use App\Support\WorkType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class RepositoryController extends Controller
{
    public function home(SearchService $search): View
    {
        $stats = Cache::remember('repository:public-counts:v2', 60, fn () => ['Jurnal' => Publikasi::published()->count(), 'Buku' => Buku::published()->count(), 'HKI' => KekayaanIntelektual::published()->count(), 'Dosen' => Dosen::active()->count()]);
        // Three bounded indexed queries; no full-table scan or unbounded UNION for the landing page.
        $latest = collect([
            ...Publikasi::published()->select([...$search->cardColumns('publikasi'), 'repository_published_at'])->with(['penulis:id,nama', 'jurnal:id,nama_jurnal', 'cover'])->orderByDesc('repository_published_at')->limit(6)->get()->all(),
            ...Buku::published()->select([...$search->cardColumns('buku'), 'updated_at'])->with(['penulis:id,nama', 'penerbit:id,nama', 'cover'])->orderByDesc('updated_at')->limit(6)->get()->all(),
            ...KekayaanIntelektual::published()->select([...$search->cardColumns('hki'), 'updated_at'])->with(['pencipta:id,nama', 'cover'])->orderByDesc('updated_at')->limit(6)->get()->all(),
        ])->sortByDesc(fn ($item) => $item instanceof Publikasi ? $item->repository_published_at : $item->updated_at)->take(6)->values();

        return view('repository.home', compact('stats', 'latest'));
    }

    public function search(Request $request, SearchService $search): View
    {
        $filters = $request->validate(WorkFilters::rules());

        return view('repository.search', ['results' => $search->publicResults($filters)] + WorkFilters::options());
    }

    public function show(string $type, int $id): View
    {
        $work = WorkType::model($type)::published()->findOrFail($id);
        $work->load([WorkType::authors($work), 'cover']);
        $documents = $work->dokumen()->where('publik', true)->when(config('repository.scan_required'), fn ($q) => $q->where('scan_status', 'clean'))->get();
        if ($type === 'publikasi') {
            $work->load(['jurnal', 'pengindeks', 'sitasi']);
        }
        if ($type === 'buku') {
            $work->load('penerbit');
        }

        return view('repository.show', compact('type', 'work', 'documents'));
    }

    public function lecturers(Request $request): View
    {
        $filters = $request->validate(['q' => 'nullable|string|max:150']);
        $lecturers = Dosen::active()->with('programStudi')->when($filters['q'] ?? null, fn ($q, $s) => $q->where('nama', 'like', '%'.$s.'%'))->orderBy('nama')->paginate(12)->withQueryString();

        return view('repository.lecturers', compact('lecturers'));
    }

    public function lecturer(Dosen $dosen, SearchService $search): View
    {
        abort_unless($dosen->aktif, 404);
        $dosen->load('programStudi');

        return view('repository.person', ['name' => $dosen->nama, 'subtitle' => $dosen->programStudi?->nama ?? 'Program studi belum dicatat', 'results' => $search->publicResults(['dosen_id' => $dosen->id])]);
    }

    public function author(Penulis $penulis, SearchService $search): View
    {
        abort_unless($penulis->aktif, 404);

        return view('repository.person', ['name' => $penulis->nama, 'subtitle' => 'Kontributor · '.ucfirst($penulis->jenis_penulis), 'results' => $search->publicResults(['penulis_id' => $penulis->id])]);
    }

    public function journal(Jurnal $jurnal, SearchService $search): View
    {
        abort_unless($jurnal->aktif, 404);

        return view('repository.person', ['name' => $jurnal->nama_jurnal, 'subtitle' => 'Jurnal · ISSN '.($jurnal->issn_cetak ?? $jurnal->issn_elektronik ?? 'belum diisi'), 'results' => $search->publicResults(['jurnal_id' => $jurnal->id])]);
    }
}
