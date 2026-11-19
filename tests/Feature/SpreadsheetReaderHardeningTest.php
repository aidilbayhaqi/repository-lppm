<?php

namespace Tests\Feature;

use App\Services\SpreadsheetReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

class SpreadsheetReaderHardeningTest extends TestCase
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private function cell(string $address, string $value, bool $prefixed = false): string
    {
        $prefix = $prefixed ? 'x:' : '';
        $escaped = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<'.$prefix.'c r="'.htmlspecialchars($address, ENT_XML1 | ENT_QUOTES, 'UTF-8').'" t="inlineStr">'
            .'<'.$prefix.'is><'.$prefix.'t>'.$escaped.'</'.$prefix.'t></'.$prefix.'is></'.$prefix.'c>';
    }

    private function worksheet(string $first, string $second, bool $prefixed = false): string
    {
        $prefix = $prefixed ? 'x:' : '';
        $namespace = $prefixed ? 'xmlns:x="'.self::NS.'"' : 'xmlns="'.self::NS.'"';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<'.$prefix.'worksheet '.$namespace.'><'.$prefix.'sheetData>'
            .'<'.$prefix.'row r="1">'.$first.'</'.$prefix.'row>'
            .'<'.$prefix.'row r="2">'.$second.'</'.$prefix.'row>'
            .'</'.$prefix.'sheetData></'.$prefix.'worksheet>';
    }

    private function file(string $xml, ?string $shared = null, bool $withRelationships = false): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'osdj-xlsx-test-');
        if ($path === false) {
            throw new \RuntimeException('Temporary test file gagal dibuat.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Temporary XLSX gagal dibuat.');
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        if ($withRelationships) {
            $zip->addFromString('xl/workbook.xml',
                '<x:workbook xmlns:x="'.self::NS.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<x:sheets><x:sheet name="Data" sheetId="1" r:id="rId1"/></x:sheets></x:workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels',
                '<r:Relationships xmlns:r="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<r:Relationship Id="rId1" Target="worksheets/sheet1.xml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"/>'
                .'</r:Relationships>');
        }
        if ($shared !== null) {
            $zip->addFromString('xl/sharedStrings.xml', $shared);
        }
        $zip->close();
        // PHPUnit process-local file; test cleanup runs even when validation throws.
        $this->beforeApplicationDestroyed(static function () use ($path): void {
            @unlink($path);
        });

        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_default_namespace_and_prefixed_namespace_are_readable(): void
    {
        foreach ([false, true] as $prefixed) {
            $prefix = $prefixed ? 'x:' : '';
            $rows = (new SpreadsheetReader)->read($this->file($this->worksheet(
                $this->cell('A1', 'judul', $prefixed).$this->cell('B1', 'penulis_nama', $prefixed),
                $this->cell('A2', 'Riset lokal', $prefixed).$this->cell('B2', 'Dewi', $prefixed),
                $prefixed
            )));
            $this->assertSame([['judul', 'penulis_nama'], ['Riset lokal', 'Dewi']], $rows);
        }
    }

    public function test_prefixed_workbook_relationships_are_readable(): void
    {
        $rows = (new SpreadsheetReader)->read($this->file($this->worksheet(
            $this->cell('A1', 'judul', true),
            $this->cell('A2', 'Dari workbook', true),
            true
        ), null, true));
        $this->assertSame([['judul'], ['Dari workbook']], $rows);
    }

    public function test_namespaced_formulas_are_rejected(): void
    {
        $formula = '<x:c r="A2"><x:f>1+1</x:f><x:v>2</x:v></x:c>';
        $file = $this->file($this->worksheet($this->cell('A1', 'judul', true), $formula, true));
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Rumus Excel tidak diimpor');
        (new SpreadsheetReader)->read($file);
    }

    public function test_invalid_cell_address_is_rejected(): void
    {
        $file = $this->file($this->worksheet($this->cell('A1', 'judul'), $this->cell('A0', 'Tidak valid')));
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Alamat sel XLSX tidak valid');
        (new SpreadsheetReader)->read($file);
    }

    public function test_duplicate_cell_is_rejected(): void
    {
        $file = $this->file($this->worksheet($this->cell('A1', 'judul'), $this->cell('A2', 'Satu').$this->cell('A2', 'Dua')));
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Alamat sel XLSX berulang');
        (new SpreadsheetReader)->read($file);
    }

    public function test_empty_but_valid_shared_strings_xml_does_not_break_inline_strings(): void
    {
        $shared = '<sst xmlns="'.self::NS.'" count="0" uniqueCount="0"/>';
        $rows = (new SpreadsheetReader)->read($this->file($this->worksheet(
            $this->cell('A1', 'judul'), $this->cell('A2', 'Nilai inline')
        ), $shared));
        $this->assertSame([['judul'], ['Nilai inline']], $rows);
    }

    public function test_invalid_shared_string_index_is_rejected_not_silently_dropped(): void
    {
        $shared = '<sst xmlns="'.self::NS.'"><si><t>judul</t></si></sst>';
        $numeric = '<c r="A2" t="s"><v>99</v></c>';
        $file = $this->file($this->worksheet($this->cell('A1', 'judul'), $numeric), $shared);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Indeks shared string XLSX tidak valid');
        (new SpreadsheetReader)->read($file);
    }
}
