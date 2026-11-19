<?php

namespace Database\Seeders;

use App\Models\AfiliasiPenulis;
use App\Models\AuditLog;
use App\Models\Buku;
use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\Jurnal;
use App\Models\KekayaanIntelektual;
use App\Models\Penerbit;
use App\Models\Pengindeks;
use App\Models\Penulis;
use App\Models\ProgramStudi;
use App\Models\Publikasi;
use App\Models\TingkatPengindeksan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Seeder demo tidak boleh dijalankan di production.');
        }
        DB::transaction(function () {
            $this->call(UserSeeder::class);
            $faculty = Fakultas::firstOrCreate(['kode' => 'DEMO-FT'], ['nama_fakultas' => 'Fakultas Teknologi (Demo)', 'aktif' => true]);
            $program = ProgramStudi::firstOrCreate(['kode' => 'DEMO-TI'], ['fakultas_id' => $faculty->id, 'nama' => 'Teknik Informatika (Demo)', 'jenjang_pendidikan' => 'S1', 'aktif' => true]);
            $lecturer = Dosen::firstOrCreate(['nidn' => 'DEMO000001'], ['program_studi_id' => $program->id, 'nama' => 'Nadia Prameswari (Demo)', 'gelar' => 'M.Kom.', 'aktif' => true]);
            $author = Penulis::firstOrCreate(['nama_normalisasi' => 'nadia prameswari demo'], ['nama' => $lecturer->nama, 'dosen_id' => $lecturer->id, 'user_id' => User::where('role', 'dosen')->first()->id, 'jenis_penulis' => 'dosen', 'aktif' => true]);
            $student = Penulis::firstOrCreate(['nama_normalisasi' => 'arka pratama demo'], ['nama' => 'Arka Pratama (Demo)', 'user_id' => User::where('role', 'mahasiswa')->first()->id, 'jenis_penulis' => 'mahasiswa', 'aktif' => true]);
            $external = Penulis::firstOrCreate(['nama_normalisasi' => 'sarah utami demo'], ['nama' => 'Sarah Utami (Demo)', 'user_id' => User::where('role', 'peneliti_eksternal')->first()->id, 'jenis_penulis' => 'eksternal', 'aktif' => true]);
            AfiliasiPenulis::firstOrCreate(['penulis_id' => $author->id, 'institusi' => 'Institusi Demonstrasi'], ['afiliasi_utama' => true]);
            $publisher = Penerbit::firstOrCreate(['kode' => 'DEMO-PUB'], ['nama' => 'Penerbit Akademik (Demo)', 'negara' => 'Indonesia', 'aktif' => true]);
            $journal = Jurnal::firstOrCreate(['nama_jurnal' => 'Jurnal Inovasi dan Masyarakat (Demo)'], ['penerbit_id' => $publisher->id, 'aktif' => true]);
            $index = Pengindeks::firstOrCreate(['nama' => 'Indeks Demonstrasi'], ['kategori' => 'lainnya', 'aktif' => true]);
            TingkatPengindeksan::firstOrCreate(['kode' => 'DEMO'], ['nama' => 'Klasifikasi demo', 'kategori' => 'lainnya', 'aktif' => true]);
            $operator = User::where('role', 'operator')->first();
            $titles = [
                'Pemanfaatan kecerdasan buatan untuk layanan administrasi akademik',
                'Literasi digital dan penguatan kapasitas usaha mikro di perkotaan',
                'Rancangan sistem energi terbarukan untuk komunitas berkelanjutan',
                'Kolaborasi perguruan tinggi dan masyarakat dalam inovasi sosial',
                'Analisis pengalaman pengguna pada layanan publik digital',
                'Pengelolaan data penelitian untuk repositori institusi',
            ];
            foreach ($titles as $i => $title) {
                $work = Publikasi::firstOrNew(['judul' => $title.' (Demo)']);
                if ($work->exists) {
                    continue;
                }
                $work->fill(['jurnal_id' => $journal->id, 'tahun_publikasi' => 2026 - ($i % 3), 'abstrak' => 'Data demonstrasi, bukan publikasi penelitian nyata. Contoh ini memperlihatkan cara menyajikan tujuan penelitian, pendekatan yang digunakan, serta kontribusi pengetahuan bagi institusi dan masyarakat. Ganti dengan metadata karya yang telah diverifikasi sebelum digunakan sebagai repositori resmi.', 'klasifikasi' => 'Penelitian', 'public_visibility' => 'public', 'academic_status' => 'published', 'volume' => '3', 'nomor' => '1', 'halaman' => '1–12']);
                $work->forceFill(['created_by' => $operator->id, 'repository_status' => $i < 4 ? 'published' : ($i === 4 ? 'submitted' : 'draft'), 'repository_published_at' => $i < 4 ? now()->subDays($i) : null])->save();
                $work->penulis()->attach([$author->id => ['urutan_penulis' => 1, 'penulis_pertama' => true, 'penulis_korespondensi' => true], $student->id => ['urutan_penulis' => 2, 'penulis_pertama' => false, 'penulis_korespondensi' => false]]);
                $work->pengindeks()->attach($index->id);
                $work->sitasi()->create(['tahun' => 2026, 'sumber' => 'lainnya', 'cakupan' => 'umum', 'jumlah_sitasi' => 0]);
                AuditLog::record($work, $operator, 'demo_seeded', [], ['status' => $work->repository_status], 'Data demonstrasi untuk mencoba antarmuka.');
            }
            $book = Buku::firstOrNew(['judul' => 'Dasar Pengelolaan Pengetahuan Digital (Demo)']);
            if (! $book->exists) {
                $book->fill(['penerbit_id' => $publisher->id, 'tahun_publikasi' => 2025, 'kategori' => 'Monograf', 'jumlah_halaman' => 180, 'public_visibility' => 'public']);
                $book->forceFill(['created_by' => $operator->id, 'status' => 'dipublikasikan'])->save();
                $book->penulis()->attach($author, ['urutan_penulis' => 1]);
            }
            $hki = KekayaanIntelektual::firstOrNew(['judul' => 'Sistem Pemetaan Kolaborasi Riset (Demo)']);
            if (! $hki->exists) {
                $hki->fill(['jenis' => 'Hak cipta', 'tahun' => 2026, 'nomor_pencatatan' => 'DEMO-HKI-001', 'public_visibility' => 'public']);
                $hki->forceFill(['created_by' => $operator->id, 'status' => 'dipublikasikan'])->save();
                $hki->pencipta()->attach([$author->id => ['urutan_pencipta' => 1], $external->id => ['urutan_pencipta' => 2]]);
            }
        });
    }
}
