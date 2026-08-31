<?php

class ImportacionPacienteService
{
    public function __construct(
        private ImportadorXlsx $xlsx,
        private ImportadorCsv $csv,
        private PacienteImportador $pacienteImportador,
        private QrystalosImportador $qrystalosImportador,
        private int $batchSize = 1000
    ) {}

    public function procesar(string $filePath, string $extension): ImportacionResultado
    {
        $resultado = new ImportacionResultado();

        $callback = function (array $lote) use ($resultado): void {
            $local = $this->pacienteImportador->importarLote($lote);

            $resultado->sumarProcesados($local['procesados']);
            $resultado->sumarOmitidos($local['omitidos']);

            // Qrystalos nunca bloquea el guardado local.
            $this->qrystalosImportador->sincronizarLote($lote, $resultado);
        };

        switch (strtolower($extension)) {
            case 'xlsx':
                $this->xlsx->procesar($filePath, $callback, $this->batchSize);
                break;

            case 'csv':
            case 'txt':
                $this->csv->procesar($filePath, $callback, $this->batchSize);
                break;

            default:
                throw new InvalidArgumentException(
                    'Formato no soportado. Use XLSX, CSV o TXT.'
                );
        }

        return $resultado;
    }
}
