<details class="publish-readiness readiness-disclosure"><summary><span>Kelengkapan karya</span><span>{{ $readiness['passed'] }} / {{ $readiness['total'] }} terpenuhi</span></summary>

    <div class="publish-readiness-header">
        <div>
            <h3 id="publish-readiness-title">
                Kesiapan Penerbitan
            </h3>

            <p class="helper">
                Persyaratan awal sebelum karya diterbitkan.
            </p>
        </div>

        <span class="readiness-badge {{ $readiness['ready'] ? 'is-ready' : 'is-pending' }}">
            {{ $readiness['ready'] ? 'Siap' : 'Belum siap' }}
        </span>
    </div>

    <ul class="readiness-list">
        @foreach ($readiness['checks'] as $check)
            <li class="readiness-item">
                <span
                    class="readiness-icon {{ $check['passed'] ? 'is-ready' : 'is-pending' }}"
                    aria-hidden="true"
                >
                    {{ $check['passed'] ? '✓' : '!' }}
                </span>

                <div class="readiness-content">
                    <strong>{{ $check['label'] }}</strong>

                    <p>
                        {{ $check['passed'] ? 'Terpenuhi' : $check['detail'] }}
                    </p>
                </div>
            </li>
        @endforeach
    </ul>

    <div class="readiness-footer">
        <strong>
            {{ $readiness['passed'] }}
            dari
            {{ $readiness['total'] }}
            pemeriksaan terpenuhi
        </strong>

        @if ($readiness['ready'])
            <p>
                Persyaratan awal telah terpenuhi.
                Pengguna yang berwenang dapat memulai penerbitan.
            </p>
        @else
            <p>
                Selesaikan persyaratan yang belum terpenuhi
                sebelum melakukan penerbitan.
            </p>
        @endif

        <p class="helper">
            Jika ada dokumen, sistem akan memeriksa file tersebut sebelum penerbitan selesai.
        </p>
    </div>
</details>
