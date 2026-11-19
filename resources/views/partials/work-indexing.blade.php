        @if ($type === 'publikasi')
            <details class="panel optional-metadata"><summary>Metadata tambahan (opsional)</summary>
                <div class="form-section-title">
                    <h2>Metadata tambahan</h2>
                </div>
                <div class="form-grid">
                    @foreach (['volume' => 'Volume', 'nomor' => 'Nomor', 'halaman' => 'Halaman', 'klasifikasi' => 'Klasifikasi', 'spmi' => 'SPMI'] as $name => $label)
                        @include('partials.field', [
                            'name' => $name,
                            'label' => $label,
                            'value' => $work->$name,
                        ])
                    @endforeach
                    @include('partials.field', [
                        'name' => 'tahun_dasar',
                        'label' => 'Tahun dasar',
                        'kind' => 'number',
                        'value' => $work->tahun_dasar,
                    ])
                    @include('partials.field', [
                        'name' => 'biaya',
                        'label' => 'Biaya (rupiah)',
                        'kind' => 'number',
                        'step' => '0.01',
                        'value' => $work->biaya,
                    ])
                    @include('partials.field', [
                        'name' => 'tautan_google_scholar',
                        'label' => 'Tautan Google Scholar',
                        'kind' => 'url',
                        'value' => $work->tautan_google_scholar,
                    ])
                </div>
            </details>
            <section class="panel">
                <div class="form-section-title">
                    <h2>Sitasi & pengindeksan</h2>
                </div>
                <h3>Sitasi tahunan</h3>
                @php($citationRows = old('citations', $work->exists ? $work->sitasi->toArray() : []))
                <div data-repeater data-next="{{ count($citationRows) }}" data-max="100">
                    <div data-rows>
                        @foreach ($citationRows as $index => $row)
                            @include('partials.citation-row')
                        @endforeach
                    </div><template data-template>@include('partials.citation-row', ['index' => '__INDEX__', 'row' => []])</template><button
                        class="button button-secondary button-small" type="button" data-add>+ Tambah sitasi</button>
                </div>
                <div class="detail-divider"></div>
                <h3>Pengindeksan jurnal</h3>
                <p class="helper">SINTA dan Scopus dapat diisi bersamaan. Gunakan peringkat yang sesuai tahun dan sumber data.</p>
                <div class="source-choice" data-source-choice>
                    <fieldset class="choice-tabs"><legend>Cara mengisi indeks</legend>@foreach(['reference'=>'Dari referensi','manual'=>'Isi manual'] as $key=>$label)<label><input type="radio" name="indexing_mode" value="{{ $key }}" @checked(old('indexing_mode', $work->indexing_mode ?? 'manual') === $key)> {{ $label }}</label>@endforeach</fieldset>
                    <div data-source-panel="reference">
                        @php($indexRows = old('indexers', $work->exists ? $work->pengindeks->map(fn($i) => ['id'=>$i->id,'tingkat_pengindeksan_id'=>$i->pivot->tingkat_pengindeksan_id,'tautan_sumber'=>$i->pivot->tautan_sumber])->toArray() : []))
                        <div data-repeater data-next="{{ count($indexRows) }}" data-max="30"><div data-rows>@foreach($indexRows as $index=>$row) @include('partials.indexer-row') @endforeach</div><template data-template>@include('partials.indexer-row', ['index'=>'__INDEX__','row'=>[]])</template><button class="button button-secondary button-small" type="button" data-add>Tambah pengindeks</button></div>
                        <p class="helper">Pilih SINTA + S1–S6, atau Scopus + Q1–Q4. Pengindeks lain tetap dapat ditambahkan.</p>
                    </div>
                    <div data-source-panel="manual" class="form-grid">
                        @include('partials.field', ['name'=>'sinta_rank','label'=>'Peringkat SINTA','kind'=>'select','value'=>$work->sinta_rank,'options'=>[1=>'SINTA 1',2=>'SINTA 2',3=>'SINTA 3',4=>'SINTA 4',5=>'SINTA 5',6=>'SINTA 6']])
                        @include('partials.field', ['name'=>'scopus_indexed','label'=>'Terindeks Scopus','kind'=>'select','value'=>(int)($work->scopus_indexed ?? false),'options'=>[0=>'Belum dicatat',1=>'Ya, terindeks'],'required'=>true])
                        @include('partials.field', ['name'=>'scopus_quartile','label'=>'Kuartil Scopus','kind'=>'select','value'=>$work->scopus_quartile,'options'=>['Q1'=>'Q1','Q2'=>'Q2','Q3'=>'Q3','Q4'=>'Q4']])
                    </div>
                </div>
                <div class="form-grid index-evidence">
                    @include('partials.field', ['name'=>'indexing_year','label'=>'Tahun data indeks','kind'=>'number','value'=>$work->indexing_year])
                    @include('partials.field', ['name'=>'indexing_source','label'=>'Tautan sumber indeks','kind'=>'url','value'=>$work->indexing_source])
                </div>

            </section>
        @endif
