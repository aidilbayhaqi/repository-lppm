@extends('layouts.repository')
@section('title', 'Persetujuan akun')
@section('content')
    <div class="page-heading">
        <div>
            <div class="eyebrow">SUPERADMIN</div>
            <h1>Persetujuan akun</h1>
            <p>{{ $pendingCount }} pengajuan menunggu keputusan. Periksa identitas sebelum mengaktifkan akun.</p>
        </div>
    </div>

    <section class="panel">
        <div class="table-wrap" role="region" aria-label="Daftar permohonan akun" tabindex="0">
            <table>
                <thead>
                    <tr>
                        <th>Pemohon</th><th>Email</th><th>Jenis</th><th>Status</th><th>Tanggal</th><th>Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $item)
                        <tr>
                            <td class="table-title">{{ $item->user->nama }}</td>
                            <td>{{ $item->user->email }}</td>
                            <td>{{ str_replace('_', ' ', $item->requested_role) }}</td>
                            <td>{{ ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$item->status] ?? $item->status }}</td>
                            <td>{{ $item->created_at->format('d M Y H:i') }}</td>
                            <td><a class="arrow-link" href="{{ route('admin.registrations.show', $item) }}">Tinjau ↗</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Belum ada pengajuan akun.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $requests->links() }}</div>
    </section>
@endsection
