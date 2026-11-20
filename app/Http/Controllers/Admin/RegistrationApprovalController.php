<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProgramStudi;
use App\Models\RegistrationRequest;
use App\Services\RegistrationApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegistrationApprovalController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-users');

        return view('admin.registrations.index', [
            'requests' => RegistrationRequest::query()
                ->with(['user:id,nama,email,is_active', 'programStudi:id,nama'])
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->latest('id')
                ->paginate(20),
            'pendingCount' => RegistrationRequest::where('status', 'pending')->count(),
        ]);
    }

    public function show(RegistrationRequest $registration): View
    {
        Gate::authorize('manage-users');

        return view('admin.registrations.show', [
            'registration' => $registration->load(['user', 'programStudi.fakultas', 'reviewer']),
            'programs' => ProgramStudi::query()
                ->where('aktif', true)
                ->whereHas('fakultas', fn ($q) => $q->where('aktif', true))
                ->with('fakultas:id,nama_fakultas')
                ->orderBy('nama')
                ->get(['id', 'nama', 'fakultas_id']),
            'assignableRoles' => RegistrationApprovalService::ROLE_LABELS,
        ]);
    }

    public function approve(Request $request, RegistrationRequest $registration, RegistrationApprovalService $service): RedirectResponse
    {
        Gate::authorize('manage-users');

        $data = $request->validate([
            'role' => ['required', Rule::in(RegistrationApprovalService::ASSIGNABLE_ROLES)],
            'identifier' => ['nullable', 'string', 'max:100'],
            'program_studi_id' => ['nullable', 'integer'],
            'review_note' => ['nullable', 'string', 'max:2000'],
            'confirm_existing_reference' => ['nullable', 'boolean'],
        ]);

        $service->approve($registration, $request->user(), $data);

        return redirect()->route('admin.registrations.index')
            ->with('success', 'Pendaftaran disetujui. Akun telah diaktifkan dan notifikasi verifikasi email dikirim.');
    }

    public function reject(Request $request, RegistrationRequest $registration, RegistrationApprovalService $service): RedirectResponse
    {
        Gate::authorize('manage-users');

        $data = $request->validate([
            'review_note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $service->reject($registration, $request->user(), $data['review_note']);

        return redirect()->route('admin.registrations.index')
            ->with('success', 'Pengajuan ditolak dan akun tetap tidak aktif.');
    }
}
