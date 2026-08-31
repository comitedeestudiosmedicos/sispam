<?php

class ImportadorCsv
{
    public function procesar(string $filePath, callable $callback, int $chunkSize = 1000): bool
    {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el archivo CSV/TXT.');
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            throw new RuntimeException('El archivo CSV/TXT está vacío.');
        }

        $sep = substr_count($firstLine, ';') > substr_count($firstLine, ',')
            ? ';'
            : ',';

        rewind($handle);

        $headerRaw = fgetcsv($handle, 4000, $sep);

        if ($headerRaw === false) {
            fclose($handle);
            throw new RuntimeException('No se pudo leer el encabezado.');
        }

        $headers = array_map(function ($h) {
            $h = preg_replace('/^\xEF\xBB\xBF/', '', (string)$h);
            return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $h)));
        }, $headerRaw);

        $batch = [];

        while (($data = fgetcsv($handle, 4000, $sep)) !== false) {
            if (count($data) === 1 && trim((string)$data[0]) === '') {
                continue;
            }

            $assoc = [];

            foreach ($headers as $idx => $header) {
                if ($header !== '') {
                    $assoc[$header] = $data[$idx] ?? '';
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

        fclose($handle);

        return true;
    }
}
