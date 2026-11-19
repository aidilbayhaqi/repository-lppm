<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

final class SpreadsheetTemplate
{
    /** Text-formatted columns preserve NIDN leading zeros and prevent Excel rounding ISBN. */
    public function create(array $headers): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lppm-template-');
        $zip = new ZipArchive;
        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Template Excel tidak dapat dibuat.');
        }
        try {
            $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            $cells = '';
            foreach ($headers as $i => $header) {
                $number = $i + 1;
                $column = '';
                while ($number > 0) {
                    $number--;
                    $column = chr(65 + $number % 26).$column;
                    $number = intdiv($number, 26);
                }
                $cells .= '<c r="'.$column.'1" t="inlineStr" s="1"><is><t>'.htmlspecialchars($header, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
            }
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<workbook xmlns="'.$ns.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Data" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/styles.xml', '<styleSheet xmlns="'.$ns.'"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="49" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
            $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="'.$ns.'"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="'.count($headers).'" width="24" customWidth="1" style="0"/></cols><sheetData><row r="1">'.$cells.'</row><row r="2"/></sheetData></worksheet>');
            if (! $zip->close()) {
                throw new RuntimeException('Template Excel tidak dapat disimpan.');
            }

            return $path;
        } catch (\Throwable $error) {
            @unlink($path);
            throw $error;
        }
    }
}
