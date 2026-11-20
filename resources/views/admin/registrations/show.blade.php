@extends('layouts.repository')
@section('title', 'Tinjau pendaftaran')
@section('content')
    <div class="page-heading">
        <div>
            <a class="breadcrumb" href="{{ route('admin.registrations.index') }}">← Persetujuan akun</a>
            <h1>Tinjau pendaftaran</h1>
            <p>Verifikasi identitas pemohon sebelum memberikan akses.</p>
        </div>
    </div>

    <section class="panel">
        <h2>Data pemohon</h2>
        <dl>
            <dt>Nama</dt><dd>{{ $registration->user->nama }}</dd>
            <dt>Email</dt><dd>{{ $registration->user->email }}</dd>
            <dt>Peran yang diminta</dt><dd>{{ str_replace('_', ' ', $registration->requested_role) }}</dd>
            <dt>NIM / NIDN / identitas</dt><dd>{{ $registration->identifier ?: '—' }}</dd>
            <dt>Program studi</dt><dd>{{ $registration->programStudi?->nama ?: '—' }}</dd>
            <dt>Status</dt><dd>{{ $registration->status }}</dd>
            @if ($registration->reviewed_at)
                <dt>Ditinjau</dt><dd>{{ $registration->reviewed_at->format('d M Y H:i') }}</dd>
                <dt>Catatan</dt><dd>{{ $registration->review_note ?: '—' }}</dd>
            @endif
        </dl>
    </section>

    @if ($registration->status === 'pending')
        <section class="panel">
            <h2>Setujui akun</h2>
            <p class="helper">Peran Superadmin tidak dapat diberikan melalui pengajuan. Jika identitas referensi sudah ada, pastikan benar milik pemohon sebelum menghubungkannya.</p>
            <form method="post" action="{{ route('admin.registrations.approve', $registration) }}">
                @csrf
                <div class="form-grid">
                    <div class="form-field">
                        <label for="role">Peran akun</label>
                        <select id="role" name="role" required>
                            @foreach ($assignableRoles as $value => $label)
                                <option value="{{ $value }}" @selected(old('role', $registration->requested_role) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('role') <p class="helper text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="form-field">
                        <label for="identifier">NIM / NIDN / identitas</label>
                        <input id="identifier" name="identifier" maxlength="100" value="{{ old('identifier', $registration->identifier) }}">
                        @error('identifier') <p class="helper text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="form-field">
                        <label for="program_studi_id">Program studi (wajib untuk dosen)</label>
                        <select name="program_studi_id" id="program_studi_id">
                            <option value="">— Pilih program studi aktif —</option>
                            @foreach ($programs as $program)
                                <option value="{{ $program->id }}" @selected((string)old('program_studi_id', $registration->program_studi_id) === (string)$program->id)>{{ $program->nama }} · {{ $program->fakultas?->nama_fakultas }}</option>
                            @endforeach
                        </select>
                        @error('program_studi_id') <p class="helper text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div class="form-field form-field-wide">
                        <label for="review_note">Catatan persetujuan (opsional)</label>
                        <textarea id="review_note" name="review_note" rows="3" maxlength="2000">{{ old('review_note') }}</textarea>
                    </div>
                </div>
                <div class="form-field">
                    <label><input name="confirm_existing_reference" type="checkbox" value="1" @checked(old('confirm_existing_reference'))> Saya sudah memeriksa kepemilikan NIM/NIDN jika referensi telah ada</label>
                    @error('confirm_existing_reference') <p class="helper text-danger">{{ $message }}</p> @enderror
                </div>
                <div class="form-actions"><button class="button" type="submit">Setujui & aktifkan akun</button></div>
            </form>
        </section>

        <section class="panel">
            <h2>Tolak pengajuan</h2>
            <form method="post" action="{{ route('admin.registrations.reject', $registration) }}">
                @csrf
                <div class="form-field">
                    <label for="reject_note">Alasan penolakan</label>
                    <textarea id="reject_note" name="review_note" rows="3" minlength="10" maxlength="2000" required>{{ old('review_note') }}</textarea>
                </div>
                <div class="form-actions"><button type="submit" class="button button-outline">Tolak pengajuan</button></div>
            </form>
        </section>
    @endif
@endsection
