<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class SpreadsheetReader
{
    public function read(UploadedFile $file): array
    {
        $rows = strtolower($file->getClientOriginalExtension()) === 'csv' ? $this->csv($file->getRealPath()) : $this->xlsx($file->getRealPath());
        if (count($rows) < 2) {
            $this->invalid('File harus berisi header dan minimal satu baris data.');
        }

        return $rows;
    }

    private function csv(string $path): array
    {
        $handle = fopen($path, 'r');
        $first = fgets($handle) ?: '';
        rewind($handle);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            foreach ($row as $value) {
                if ($value !== null && ! mb_check_encoding($value, 'UTF-8')) {
                    fclose($handle);
                    $this->invalid('Simpan CSV dalam format UTF-8.');
                }
            }
            if (count($rows) >= 1001 || count($row) > 50) {
                fclose($handle);
                $this->invalid('Batas import 1.000 baris dan 50 kolom.');
            }
            $rows[] = array_map(fn ($v) => trim((string) $v), $row);
        }
        fclose($handle);
        if (isset($rows[0][0])) {
            $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]);
        }

        return $rows;
    }

    private function xml(string $value): \SimpleXMLElement
    {
        if (stripos($value, '<!DOCTYPE') !== false || stripos($value, '<!ENTITY') !== false) {
            $this->invalid('XML dengan entity tidak diizinkan.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($value, \SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if ($xml === false) {
            $this->invalid('Struktur XLSX tidak valid.');
        }

        return $xml;
    }

    private function xlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->invalid('File XLSX tidak dapat dibuka.');
        }
        try {
            $total = 0;
            if ($zip->numFiles > 500) {
                $this->invalid('Arsip XLSX terlalu kompleks.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $total += $entry['size'];
                if ($total > 20 * 1024 * 1024 || str_contains($entry['name'], 'externalLinks/') || str_ends_with(strtolower($entry['name']), '.bin')) {
                    $this->invalid('XLSX terlalu besar atau mengandung macro/tautan eksternal.');
                }
            }
            [$sheetPath, $date1904] = $this->firstSheet($zip);
            $dateStyles = $this->dateStyles($zip);
            $sheet = $zip->getFromName($sheetPath);
            if ($sheet === false) {
                $this->invalid('Gunakan template dengan data pada sheet pertama.');
            }
            $strings = [];
            $shared = $zip->getFromName('xl/sharedStrings.xml');
            if ($shared !== false) {
                foreach ($this->xml($shared)->xpath('//*[local-name()="si"]') as $item) {
                    $strings[] = implode('', array_map(fn ($t) => (string) $t, $item->xpath('.//*[local-name()="t"]')));
                }
            }
            $rows = [];
            foreach ($this->xml($sheet)->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                $line = (int) $row['r'];
                if ($line < 1 || $line > 1001 || $line <= count($rows)) {
                    $this->invalid('Nomor baris XLSX tidak valid atau melebihi batas 1.000 baris data.');
                }
                while (count($rows) < $line - 1) {
                    $rows[] = [];
                }
                $values = [];
                foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                    if ($cell->getName() !== 'c') {
                        continue;
                    }
                    if ($cell->xpath('./*[local-name()="f"]')) {
                        $this->invalid('Rumus Excel tidak diimpor. Tempel hasil sebagai nilai sebelum mengunggah.');
                    }
                    $address = trim((string) $cell['r']);
                    $match = [];
                    if (preg_match('/^([A-Z]+)([1-9][0-9]*)$/D', $address, $match) !== 1 || (int) $match[2] !== $line) {
                        $this->invalid(sprintf('Alamat sel XLSX tidak valid (baris %d, alamat: %s).',
                            $line, $address === '' ? '(kosong)' : substr($address, 0, 20)));
                    }
                    $col = 0;
                    foreach (str_split($match[1]) as $letter) {
                        $col = $col * 26 + ord($letter) - 64;
                    }
                    if ($col > 50) {
                        $this->invalid('Batas import 50 kolom.');
                    }
                    if (array_key_exists($col - 1, $values)) {
                        $this->invalid('Alamat sel XLSX berulang.');
                    }
                    $type = (string) $cell['t'];
                    if ($type === 's') {
                        $index = trim((string) $cell->v);
                        if (preg_match('/^(0|[1-9][0-9]*)$/D', $index) !== 1 || ! array_key_exists((int) $index, $strings)) {
                            $this->invalid('Indeks shared string XLSX tidak valid.');
                        }
                        $value = $strings[(int) $index];
                    } elseif ($type === 'inlineStr') {
                        $value = implode('', array_map(fn ($t) => (string) $t, $cell->xpath('.//*[local-name()="t"]')));
                    } else {
                        $value = (string) $cell->v;
                    }
                    if (($type === '' || $type === 'n') && isset($dateStyles[(int) $cell['s']]) && is_numeric($value)) {
                        $serial = (int) floor((float) $value);
                        if ($serial < 0 || $serial > 2957003 || (! $date1904 && $serial === 60)) {
                            $this->invalid('Tanggal Excel tidak valid. Gunakan YYYY-MM-DD.');
                        }
                        $epoch = $date1904 ? '1904-01-01' : ($serial < 60 ? '1899-12-31' : '1899-12-30');
                        $value = (new \DateTimeImmutable($epoch, new \DateTimeZone('UTC')))->modify('+'.$serial.' days')->format('Y-m-d');
                    }
                    $values[$col - 1] = trim($value);
                }
                if (count($rows) >= 1001) {
                    $this->invalid('Batas import 1.000 baris.');
                }
                $dense = array_fill(0, $values ? max(array_keys($values)) + 1 : 0, '');
                foreach ($values as $i => $v) {
                    $dense[$i] = $v;
                }
                $rows[] = $dense;
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function firstSheet(ZipArchive $zip): array
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        if ($workbook === false) {
            return ['xl/worksheets/sheet1.xml', false];
        }
        $xml = $this->xml($workbook);
        $first = $xml->xpath('//*[local-name()="sheets"]/*[local-name()="sheet"]')[0] ?? null;
        $id = (string) ($first?->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($id === '' || $rels === false) {
            $this->invalid('Relasi sheet Excel tidak valid.');
        }
        $properties = $xml->xpath('//*[local-name()="workbookPr"]')[0] ?? null;
        $date1904 = in_array((string) ($properties['date1904'] ?? ''), ['1', 'true'], true);
        foreach ($this->xml($rels)->xpath('./*[local-name()="Relationship"]') ?: [] as $relation) {
            if ((string) $relation['TargetMode'] === 'External') {
                $this->invalid('Tautan eksternal Excel tidak diizinkan.');
            }
            if ((string) $relation['Id'] === $id) {
                $target = (string) $relation['Target'];
                $path = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
            }
        }
        if (! isset($path) || ! preg_match('~^xl/worksheets/[a-zA-Z0-9_-]+\.xml$~', $path)) {
            $this->invalid('Lokasi sheet Excel tidak valid.');
        }

        return [$path, $date1904];
    }

    private function dateStyles(ZipArchive $zip): array
    {
        $styles = $zip->getFromName('xl/styles.xml');
        if ($styles === false) {
            return [];
        }
        $xml = $this->xml($styles);
        $formats = [];
        foreach ($xml->xpath('//*[local-name()="numFmt"]') as $format) {
            $code = preg_replace('/"[^"\r\n]*"|\[[^\]]*\]/', '', (string) $format['formatCode']);
            if (preg_match('/[yd]/i', $code)) {
                $formats[(int) $format['numFmtId']] = true;
            }
        }
        $result = [];
        foreach ($xml->xpath('//*[local-name()="cellXfs"]/*[local-name()="xf"]') as $i => $style) {
            $id = (int) $style['numFmtId'];
            if (in_array($id, [14, 15, 16, 17, 22], true) || isset($formats[$id])) {
                $result[$i] = true;
            }
        }

        return $result;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
