<?php

class ImportacionResultado
{
    private int $procesados = 0;
    private int $omitidos = 0;
    private int $qrystalosOk = 0;
    private int $qrystalosError = 0;
    private array $erroresQrystalos = [];

    public function sumarProcesados(int $cantidad): void
    {
        $this->procesados += $cantidad;
    }

    public function sumarOmitidos(int $cantidad): void
    {
        $this->omitidos += $cantidad;
    }

    public function qrystalosOk(): void
    {
        $this->qrystalosOk++;
    }

    public function qrystalosError(string $detalle, int $maxErrores = 5): void
    {
        $this->qrystalosError++;
        if (count($this->erroresQrystalos) < $maxErrores) {
            $this->erroresQrystalos[] = $detalle;
        }
    }

    public function toArray(float $tiempo, bool $qrystalosHabilitado): array
    {
        return [
            'procesados' => $this->procesados,
            'omitidos' => $this->omitidos,
            'total' => $this->procesados + $this->omitidos,
            'tiempo' => $tiempo,
            'qrystalos_habilitado' => $qrystalosHabilitado,
            'qrystalos_ok' => $this->qrystalosOk,
            'qrystalos_error' => $this->qrystalosError,
            'errores_qrystalos' => $this->erroresQrystalos,
        ];
    }
}
