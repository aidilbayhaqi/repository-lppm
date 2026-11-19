<div class="repeater-row" data-row>
    <select data-lookup="{{ route('lookups', 'pengindeks') }}" aria-label="Pengindeks"
        name="indexers[{{ $index }}][id]">
        <option value="">Pilih pengindeks</option>
        @foreach ($indexers as $item)
            <option value="{{ $item->id }}" @selected(($row['id'] ?? '') == $item->id)>{{ $item->nama }}</option>
        @endforeach
    </select>
    <select data-lookup="{{ route('lookups', 'tingkat-pengindeksan') }}" aria-label="Tingkat pengindeksan"
        name="indexers[{{ $index }}][tingkat_pengindeksan_id]">
        <option value="">Tanpa tingkat</option>
        @foreach ($levels as $level)
            <option value="{{ $level->id }}" @selected(($row['tingkat_pengindeksan_id'] ?? '') == $level->id)>{{ $level->nama }}</option>
        @endforeach
    </select>
    <input
    aria-label="Tautan bukti pengindeksan"
    type="url"
    name="indexers[{{ $index }}][tautan_sumber]"
    maxlength="500"
    placeholder="https://example.org/artikel"
    value="{{ $row['tautan_sumber'] ?? '' }}"
    >
        <button type="button" class="remove-row" data-remove>Hapus</button>
</div>
