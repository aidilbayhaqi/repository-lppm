<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkflowRequest;
use App\Models\PublishAttempt;
use App\Services\PublicationWorkflow;
use App\Support\WorkType;
use Illuminate\Http\RedirectResponse;

class WorkflowController extends Controller
{
    public function store(
        WorkflowRequest $request,
        string $type,
        int $id,
        PublicationWorkflow $service
    ): RedirectResponse {
        $work = $service->transition(
            WorkType::model($type)::findOrFail($id),
            $request->user(),
            $request->validated()
        );

        $redirect = redirect()->route(
            'admin.works.show',
            [$type, $id]
        );

        // Khusus penerbitan publikasi:
        // tampilkan hasil akhirnya secara langsung.
        if (
            $request->validated('action') === 'publish' &&
            $type === 'publikasi'
        ) {
            if ($work->repository_status === 'published') {
                return $redirect->with(
                    'success',
                    'Jurnal berhasil diterbitkan.'
                );
            }

            // Ambil alasan kegagalan terbaru.
            $message = PublishAttempt::query()
                ->where('publikasi_id', $id)
                ->latest('id')
                ->value('error_message');

            return $redirect->with(
                'warning',
                $message
                    ?: 'Penerbitan belum berhasil. Periksa riwayat penerbitan.'
            );
        }

        return $redirect->with(
            'success',
            'Status karya berhasil diperbarui.'
        );
    }
}
