<div class="repeater-row" data-row>
    <input aria-label="Tahun sitasi" name="citations[{{ $index }}][tahun]" type="number" min="1901"
        max="{{ date('Y') }}" placeholder="Tahun" value="{{ $row['tahun'] ?? '' }}"><select aria-label="Sumber sitasi"
        name="citations[{{ $index }}][sumber]">
        @foreach (['google_scholar' => 'Google Scholar', 'scopus' => 'Scopus', 'sinta' => 'SINTA', 'lainnya' => 'Lainnya'] as $key => $label)
            <option value="{{ $key }}" @selected(($row['sumber'] ?? 'google_scholar') === $key)>{{ $label }}</option>
        @endforeach
    </select>
    <select aria-label="Cakupan sitasi" name="citations[{{ $index }}][cakupan]">
        @foreach (['umum', 'nasional', 'internasional'] as $scope)
            <option @selected(($row['cakupan'] ?? 'umum') === $scope)>{{ $scope }}</option>
        @endforeach
    </select><input aria-label="Jumlah sitasi" name="citations[{{ $index }}][jumlah_sitasi]" type="number"
        min="0" placeholder="Jumlah" value="{{ $row['jumlah_sitasi'] ?? 0 }}"><button type="button"
        class="remove-row" data-remove>Hapus</button>
</div>
