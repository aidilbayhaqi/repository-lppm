@php($mode = $row['mode'] ?? (!empty($row['id']) ? 'reference' : 'manual'))
<div class="author-entry" data-row data-author-entry>
    <div class="source-choice" data-source-choice>
        <fieldset class="choice-tabs"><legend>Data penulis</legend>
            @foreach(['manual'=>'Penulis baru','reference'=>'Pilih rekomendasi'] as $key=>$label)
                <label><input type="radio" name="authors[{{ $index }}][mode]" value="{{ $key }}" @checked($mode === $key)> {{ $label }}</label>
            @endforeach
        </fieldset>
        <div data-source-panel="reference">
            <label for="author-{{ $index }}">Nama penulis yang tersedia</label>
            <select id="author-{{ $index }}" name="authors[{{ $index }}][id]" data-lookup="{{ route('lookups','penulis') }}">
                <option value="">Cari atau pilih nama</option>
                @foreach($authors as $author)<option value="{{ $author->id }}" @selected(($row['id'] ?? '') == $author->id)>{{ $author->nama }} · {{ ['dosen'=>'Dosen','mahasiswa'=>'Mahasiswa','eksternal'=>'Eksternal'][$author->jenis_penulis] ?? 'Penulis' }} · #{{ $author->id }}</option>@endforeach
            </select>
        </div>
        <div data-source-panel="manual" class="form-grid">
            <div class="form-field"><label for="author-name-{{ $index }}">Nama penulis *</label><input id="author-name-{{ $index }}" name="authors[{{ $index }}][nama]" value="{{ $row['nama'] ?? '' }}" maxlength="150" required></div>
            <div class="form-field"><label for="author-kind-{{ $index }}">Jenis penulis *</label><select id="author-kind-{{ $index }}" name="authors[{{ $index }}][jenis_penulis]" data-author-kind required>
                @foreach(['mahasiswa'=>'Mahasiswa','dosen'=>'Dosen','eksternal'=>'Penulis eksternal'] as $key=>$label)<option value="{{ $key }}" @selected(($row['jenis_penulis'] ?? 'mahasiswa') === $key)>{{ $label }}</option>@endforeach
            </select></div>
            <div class="form-field" data-author-identity><label for="author-identity-{{ $index }}">NIM / identitas (opsional)</label><input id="author-identity-{{ $index }}" name="authors[{{ $index }}][identitas]" value="{{ $row['identitas'] ?? '' }}" maxlength="100"></div>
            <div class="form-field" data-author-dosen><label for="author-nidn-{{ $index }}">NIDN *</label><input id="author-nidn-{{ $index }}" name="authors[{{ $index }}][nidn]" value="{{ $row['nidn'] ?? '' }}" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" data-nidn><p class="helper">10 digit. Nama dan NIDN yang cocok memakai data dosen yang sama.</p></div>
            <div class="form-field form-field-wide author-affiliation" data-author-dosen>
                <label for="author-program-{{ $index }}">Program studi dosen (Data Referensi)</label>
                <select id="author-program-{{ $index }}" name="authors[{{ $index }}][program_studi_id]" data-lookup="{{ route('lookups', 'program-studi') }}">
                    <option value="">Pilih program studi dari Data Referensi</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id }}" @selected(($row['program_studi_id'] ?? '') == $program->id)>{{ $program->nama }}</option>
                    @endforeach
                </select>
                <p class="helper">Wajib untuk dosen. Jika belum tersedia, minta Admin menambahkan Fakultas/Prodi di Data Referensi.</p>
            </div>

        </div>
    </div>
    <div class="author-footer">
        <div class="author-flags">
            @if($type !== 'hki')<label><input type="checkbox" name="authors[{{ $index }}][corresponding]" value="1" @checked($row['corresponding'] ?? false)> Korespondensi</label>@endif
            @if($type === 'publikasi')<label><input type="checkbox" name="authors[{{ $index }}][principal]" value="1" @checked($row['principal'] ?? false)> Peneliti utama</label>@endif
        </div>
        <div><button type="button" class="text-button" data-move="up" aria-label="Naikkan urutan penulis">↑ Naikkan</button><button type="button" class="text-button text-danger" data-remove>Hapus</button></div>
    </div>
</div>
