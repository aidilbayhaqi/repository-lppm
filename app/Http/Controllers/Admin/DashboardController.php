<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, SearchService $search): View
    {
        $base = $search->query('publikasi', [], $request->user());
        $counts = (clone $base)->selectRaw('repository_status, count(*) as total')->groupBy('repository_status')->pluck('total', 'repository_status');
        $stats = ['Jurnal' => $counts->sum(), 'Buku' => $search->query('buku', [], $request->user())->count(), 'HKI' => $search->query('hki', [], $request->user())->count(), 'Menunggu verifikasi' => ($counts['submitted'] ?? 0) + ($counts['pending_verification'] ?? 0)];
        $recent = (clone $base)->select(['id', 'judul', 'repository_status', 'updated_at'])->with('penulis:id,nama')->latest('updated_at')->take(6)->get();
        $endYear = now()->year;
        $totals = (clone $base)->whereBetween('tahun_publikasi', [$endYear - 5, $endYear])->selectRaw('tahun_publikasi, count(*) as total')->groupBy('tahun_publikasi')->pluck('total', 'tahun_publikasi');
        $yearly = collect(range($endYear - 5, $endYear))->map(fn (int $year) => (object) ['tahun_publikasi' => $year, 'total' => (int) ($totals[$year] ?? 0)]);

        return view('admin.dashboard', compact('stats', 'counts', 'recent', 'yearly'));
    }
}
