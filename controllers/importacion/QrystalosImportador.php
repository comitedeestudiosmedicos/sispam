<?php

class QrystalosImportador
{
    public function __construct(
        private Paciente $pacienteModel,
        private QrystalosSyncService $qrystalosService,
        private bool $habilitado,
        private int $maxErrores = 5
    ) {}

    public function sincronizarLote(
        array $lote,
        ImportacionResultado $resultado
    ): void {
        if (!$this->habilitado) {
            return;
        }

        foreach ($lote as $indice => $fila) {
            $tipoDocumento = strtoupper(trim((string)($fila['tipo_documento'] ?? 'CC')));
            $numeroDocumento = trim((string)($fila['numero_documento'] ?? ''));

            if ($numeroDocumento === '') {
                continue;
            }

            try {
                $paciente = $this->pacienteModel->getByDocumento(
                    $tipoDocumento,
                    $numeroDocumento
                );

                $idQrystalosExistente = $paciente
                    ? ($paciente['qrystalos_consecutivo'] ?? null)
                    : null;

                $datos = (new PacienteMapper())->map($fila);

                $respuesta = $this->qrystalosService->sincronizarPaciente(
                    $datos,
                    $idQrystalosExistente
                );

                if (!empty($respuesta['success'])) {
                    $resultado->qrystalosOk();

                    if (!empty($respuesta['consecutivo'])) {
                        $this->pacienteModel->actualizarQrystalosId(
                            $tipoDocumento,
                            $numeroDocumento,
                            $respuesta['consecutivo']
                        );
                    }
                } else {
                    $resultado->qrystalosError(
                        'Registro ' . ($indice + 1) .
                        ' (Doc: ' . $numeroDocumento . '): ' .
                        ($respuesta['error'] ?? 'Respuesta no especificada de Qrystalos'),
                        $this->maxErrores
                    );
                }
            } catch (Throwable $e) {
                $resultado->qrystalosError(
                    'Registro ' . ($indice + 1) .
                    ' (Doc: ' . $numeroDocumento . '): ' . $e->getMessage(),
                    $this->maxErrores
                );
            }
        }
    }
}
