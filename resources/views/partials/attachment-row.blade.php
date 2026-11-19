<div class="attachment-entry" data-row data-document-fields>
    <div class="attachment-heading"><h3 data-document-heading>{{ \App\Support\DocumentTypes::label($row['jenis_dokumen'] ?? 'dokumen_utama') }}</h3><button type="button" class="text-button text-danger" data-remove>Hapus</button></div>
    <div class="form-grid">
        <div class="form-field"><label for="attachment-kind-{{ $index }}">Jenis file</label><select id="attachment-kind-{{ $index }}" name="attachments[{{ $index }}][jenis_dokumen]" data-document-kind>@foreach(\App\Support\DocumentTypes::OPTIONS as $key=>$label)<option value="{{ $key }}" @selected(($row['jenis_dokumen'] ?? 'dokumen_utama') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="form-field"><label for="attachment-access-{{ $index }}">Akses file</label><select id="attachment-access-{{ $index }}" name="attachments[{{ $index }}][publik]"><option value="0" @selected(!($row['publik'] ?? false))>Privat tim karya</option><option value="1" @selected($row['publik'] ?? false)>Publik setelah karya terbit</option></select></div>
    </div>
    <label class="file-drop" for="attachment-file-{{ $index }}"><x-icon name="upload" size="24" /><span>Pilih file PDF <small>Label file mengikuti jenis yang dipilih di atas.</small></span><input id="attachment-file-{{ $index }}" type="file" name="attachments[{{ $index }}][file]" accept=".pdf,application/pdf"></label>
</div>
