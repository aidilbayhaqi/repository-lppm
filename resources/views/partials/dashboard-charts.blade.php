<div class="dashboard-charts">
    <section class="panel chart-panel" aria-labelledby="year-chart-title">
        <div class="panel-heading">
            <div>
                <h2 id="year-chart-title">Jurnal per tahun</h2>
                <p>Berdasarkan tahun publikasi, termasuk draf.</p>
            </div><span
                class="chart-period">{{ $yearly->first()->tahun_publikasi }}–{{ $yearly->last()->tahun_publikasi }}</span>
        </div>
        @php($maximum = max(1, $yearly->max('total')))
        <div class="chart-scroll" tabindex="0" role="region"
            aria-label="Grafik jurnal enam tahun terakhir, dapat digeser">
            <svg viewBox="0 0 600 210" class="year-chart" role="img" aria-labelledby="year-chart-caption">
                <title id="year-chart-caption">Jumlah jurnal per tahun:
                    {{ $yearly->map(fn($row) => $row->tahun_publikasi . ': ' . $row->total)->join(', ') }}</title>
                <line x1="20" y1="170" x2="580" y2="170" class="chart-gridline" />
                @foreach ($yearly as $row)
                    @php($height = (126 * $row->total) / $maximum)
                    <g>
                        <rect x="{{ 40 + $loop->index * 92 }}" y="{{ 170 - $height }}" width="52"
                            height="{{ $height }}" rx="4" class="chart-bar">
                            <title>{{ $row->tahun_publikasi }}: {{ $row->total }} jurnal</title>
                        </rect>
                        <text x="{{ 66 + $loop->index * 92 }}" y="{{ 158 - $height }}" text-anchor="middle"
                            class="chart-value">{{ $row->total }}</text>
                        <text x="{{ 66 + $loop->index * 92 }}" y="198" text-anchor="middle"
                            class="chart-label">{{ $row->tahun_publikasi }}</text>
                    </g>
                @endforeach
            </svg>
        </div>
        <details class="chart-data">
            <summary>Lihat angka per tahun</summary>
            <table>
                <thead>
                    <tr>
                        <th>Tahun</th>
                        <th>Jurnal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($yearly as $row)
                        <tr>
                            <td>{{ $row->tahun_publikasi }}</td>
                            <td>{{ $row->total }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    </section>
    <section class="panel chart-panel" aria-labelledby="collection-chart-title">
        <h2 id="collection-chart-title">Komposisi karya</h2>
        <p class="helper">Seluruh status, sesuai akses akun Anda.</p>
        @php($collections = array_slice($stats, 0, 3, true))
        @php($total = array_sum($collections))
        <div class="collection-bars">
            @foreach ($collections as $label => $count)
                <div class="collection-bar">
                    <div><span>{{ $label }}</span><strong>{{ number_format($count, 0, ',', '.') }}
                            <small>{{ $total ? number_format(($count / $total) * 100, 1, ',', '.') : 0 }}%</small></strong>
                    </div><progress value="{{ $count }}" max="{{ max(1, $total) }}"
                        aria-label="{{ $label }}: {{ $count }} dari {{ $total }} karya"></progress>
                </div>
            @endforeach
        </div>
        @if (!$total)
            <p class="helper">Grafik akan terisi setelah karya ditambahkan.</p>
        @endif
    </section>
</div>
