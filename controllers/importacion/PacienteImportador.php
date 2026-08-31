<?php

class PacienteImportador
{
    public function __construct(
        private Paciente $pacienteModel,
        private ImportacionValidacion $validacion,
        private PacienteMapper $mapper
    ) {}

    public function importarLote(array $lote): array
    {
        $procesados = 0;
        $omitidos = 0;

        foreach ($lote as $fila) {
            $validacion = $this->validacion->validar($fila);

            if (!$validacion['valido']) {
                $omitidos++;
                continue;
            }

            try {
                $datos = $this->mapper->map($fila);
                $this->pacienteModel->createOrUpdate($datos);
                $procesados++;
            } catch (Throwable $e) {
                $omitidos++;
            }
        }

        return [
            'procesados' => $procesados,
            'omitidos' => $omitidos,
        ];
    }
}
