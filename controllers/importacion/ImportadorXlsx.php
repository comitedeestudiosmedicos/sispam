<?php

class ImportadorXlsx
{
    public function procesar(string $filePath, callable $callback, int $chunkSize = 1000): bool
    {
        $zip = new ZipArchive();

        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('No se pudo abrir el archivo XLSX.');
        }

        $sharedStrings = [];
        $sstXml = $zip->getFromName('xl/sharedStrings.xml');

        if ($sstXml !== false) {
            $reader = new XMLReader();
            $reader->XML($sstXml);

            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'si') {
                    $siXml = $reader->readOuterXML();
                    preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $siXml, $matches);
                    $sharedStrings[] = html_entity_decode(
                        implode('', $matches[1]),
                        ENT_QUOTES | ENT_XML1,
                        'UTF-8'
                    );
                }
            }

            $reader->close();
        }

        $sheetStream = $zip->getStream('xl/worksheets/sheet1.xml');

        if (!$sheetStream) {
            $zip->close();
            throw new RuntimeException('No se encontró la primera hoja del XLSX.');
        }

        $tempSheet = tempnam(sys_get_temp_dir(), 'sispam_sheet_');
        $fp = fopen($tempSheet, 'w');

        while (!feof($sheetStream)) {
            fwrite($fp, fread($sheetStream, 65536));
        }

        fclose($fp);
        fclose($sheetStream);
        $zip->close();

        $reader = new XMLReader();

        if (!$reader->open($tempSheet)) {
            @unlink($tempSheet);
            throw new RuntimeException('No se pudo leer la hoja XLSX.');
        }

        $rowIdx = 0;
        $headers = [];
        $batch = [];

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'row') {
                continue;
            }

            $rowXml = $reader->readOuterXML();
            $rowCells = $this->extractCells($rowXml, $sharedStrings);
            $rowIdx++;

            if ($rowIdx === 1) {
                $headers = array_map(
                    fn($h) => strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $h))),
                    $rowCells
                );
                continue;
            }

            $assoc = [];

            foreach ($headers as $colIdx => $headerName) {
                if ($headerName !== '') {
                    $assoc[$headerName] = $rowCells[$colIdx] ?? '';
                }
            }

            $batch[] = $assoc;

            if (count($batch) >= $chunkSize) {
                $callback($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $callback($batch);
        }

        $reader->close();
        @unlink($tempSheet);

        return true;
    }

    private function extractCells(string $rowXml, array &$sharedStrings): array
    {
        $cells = [];
        $reader = new XMLReader();
        $reader->XML($rowXml);

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'c') {
                continue;
            }

            $rAttr = $reader->getAttribute('r');
            $tAttr = $reader->getAttribute('t');
            $colIdx = $this->colLetterToIndex(
                preg_replace('/\d+/', '', $rAttr)
            );

            $val = '';
            $cellXml = $reader->readOuterXML();

            if ($tAttr === 's') {
                if (preg_match('/<v>(.*?)<\/v>/', $cellXml, $m)) {
                    $val = $sharedStrings[(int)$m[1]] ?? '';
                }
            } elseif ($tAttr === 'inlineStr') {
                if (preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $cellXml, $m)) {
                    $val = html_entity_decode(
                        implode('', $m[1]),
                        ENT_QUOTES | ENT_XML1,
                        'UTF-8'
                    );
                }
            } elseif (preg_match('/<v>(.*?)<\/v>/', $cellXml, $m)) {
                $val = trim($m[1]);
            }

            $cells[$colIdx] = $val;
        }

        $reader->close();

        if (empty($cells)) {
            return [];
        }

        $ordered = [];
        $maxCol = max(array_keys($cells));

        for ($i = 0; $i <= $maxCol; $i++) {
            $ordered[$i] = $cells[$i] ?? '';
        }

        return $ordered;
    }

    private function colLetterToIndex(string $colStr): int
    {
        $colStr = strtoupper($colStr);
        $idx = 0;

        for ($i = 0, $len = strlen($colStr); $i < $len; $i++) {
            $idx = $idx * 26 + (ord($colStr[$i]) - 64);
        }

        return $idx - 1;
    }
}
