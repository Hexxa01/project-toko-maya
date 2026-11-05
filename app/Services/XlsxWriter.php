<?php

namespace App\Services;

use XMLWriter;
use ZipArchive;

class XlsxWriter
{
    /** A minimal OOXML workbook: typed numeric cells and literal inline strings, never formulas. */
    public function create(iterable $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'maya-xlsx-');
        $sheet = tempnam(sys_get_temp_dir(), 'maya-sheet-');
        try {
            $xml = new XMLWriter;
            $xml->openUri($sheet);
            $xml->startDocument('1.0', 'UTF-8');
            $xml->startElementNS(null, 'worksheet', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $xml->startElement('sheetData');
            foreach ($rows as $row) {
                $xml->startElement('row');
                foreach ($row as $value) {
                    $xml->startElement('c');
                    if (is_int($value) || is_float($value)) {
                        $xml->writeElement('v', (string) $value);
                    } else {
                        $xml->writeAttribute('t', 'inlineStr');
                        $xml->startElement('is');
                        $xml->writeElement('t', preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $value) ?? '');
                        $xml->endElement();
                    }$xml->endElement();
                }$xml->endElement();
            }
            $xml->endElement();
            $xml->endElement();
            $xml->endDocument();
            $xml->flush();
            unset($xml);
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Tidak dapat membuat workbook.');
            }
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Penjualan" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            $zip->addFile($sheet, 'xl/worksheets/sheet1.xml');
            if (! $zip->close()) {
                throw new \RuntimeException('Workbook gagal disimpan.');
            }

            return $path;
        } catch (\Throwable $e) {
            @unlink($path);
            throw $e;
        } finally {
            @unlink($sheet);
        }
    }
}
