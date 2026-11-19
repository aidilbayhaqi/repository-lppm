<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportRequest;
use App\Models\ImportBatch;
use App\Models\Penulis;
use App\Services\ImportService;
use App\Services\SpreadsheetReader;
use App\Services\SpreadsheetTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.import.index', ['batches' => ImportBatch::where('user_id', $request->user()->id)->latest()->paginate(10)]);
    }

    public function template(Request $request, string $type, ImportService $service): StreamedResponse|BinaryFileResponse
    {
        $headers = $service->headers($type);
        if ($request->query('format') !== 'csv') {
            return response()->download(app(SpreadsheetTemplate::class)->create($headers), 'template-'.$type.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
        }

        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers, ',', '"', '');
            fclose($out);
        }, 'template-'.$type.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function preview(ImportRequest $request, ImportService $service, SpreadsheetReader $reader): RedirectResponse
    {
        $batch = $service->preview($request->validated('type'), $reader->read($request->file('file')), $request->user(), $request->file('file')->getClientOriginalName());

        return redirect()->route('admin.import.show', $batch);
    }

    public function show(Request $request, ImportBatch $batch): View
    {
        abort_unless($batch->user_id === $request->user()->id, 403);

        $authorNames = Penulis::whereIn('id', collect($batch->rows)->flatMap(fn ($row) => array_column($row['data']['authors'], 'id'))->unique())->pluck('nama', 'id');

        return view('admin.import.show', compact('batch', 'authorNames'));
    }

    public function commit(Request $request, ImportBatch $batch, ImportService $service): RedirectResponse
    {
        $count = $service->commit($batch, $request->user());

        return redirect()->route('admin.import.show', $batch)->with('success', $count ? "$count karya berhasil diimpor sebagai draf." : 'Batch sudah pernah diimpor. Tidak ada data ganda yang dibuat.');
    }
}
