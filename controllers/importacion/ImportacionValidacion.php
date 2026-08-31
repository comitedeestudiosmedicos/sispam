<?php

class ImportacionValidacion
{
    public function validar(array $fila): array
    {
        $errores = [];

        $documento = trim((string)($fila['numero_documento'] ?? ''));
        $primerNombre = trim((string)($fila['primer_nombre'] ?? ''));
        $primerApellido = trim((string)($fila['primer_apellido'] ?? ''));

        if ($documento === '') {
            $errores[] = 'Número de documento vacío.';
        }

        if ($primerNombre === '') {
            $errores[] = 'Primer nombre vacío.';
        }

        if ($primerApellido === '') {
            $errores[] = 'Primer apellido vacío.';
        }

        return [
            'valido' => empty($errores),
            'errores' => $errores,
        ];
    }
}
