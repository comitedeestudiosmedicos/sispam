<?php

class PacienteMapper
{
    public function map(array $fila): array
    {
        $tipoDocumento = strtoupper(trim((string)($fila['tipo_documento'] ?? 'CC')));
        $numeroDocumento = preg_replace(
            '/[^\dA-Za-z]/',
            '',
            trim((string)($fila['numero_documento'] ?? ''))
        );

        $fecha = trim((string)($fila['fecha_nacimiento'] ?? ''));
        if ($fecha !== '') {
            $timestamp = strtotime($fecha);
            $fecha = $timestamp !== false ? date('Y-m-d', $timestamp) : null;
        } else {
            $fecha = null;
        }

        $sexo = trim((string)($fila['sexo'] ?? 'Masculino'));
        if (!in_array($sexo, [
            'Masculino',
            'Femenino',
            'Indeterminado o Intersexual'
        ], true)) {
            $sexo = 'Masculino';
        }

        $primerNombre = trim((string)($fila['primer_nombre'] ?? ''));
        $segundoNombre = trim((string)($fila['segundo_nombre'] ?? ''));
        $primerApellido = trim((string)($fila['primer_apellido'] ?? ''));
        $segundoApellido = trim((string)($fila['segundo_apellido'] ?? ''));
        $celular = trim((string)($fila['numero_celular'] ?? ''));

        return [
            'tipo_documento'         => $tipoDocumento,
            'numero_documento'       => $numeroDocumento,
            'primer_nombre'          => $primerNombre,
            'segundo_nombre'         => $segundoNombre,
            'primer_apellido'        => $primerApellido,
            'segundo_apellido'       => $segundoApellido,
            'nombres'                => trim($primerNombre . ' ' . $segundoNombre),
            'apellidos'              => trim($primerApellido . ' ' . $segundoApellido),
            'fecha_nacimiento'       => $fecha,
            'sexo'                  => $sexo,
            'estado_civil'          => trim((string)($fila['estado_civil'] ?? '')),
            'grupo_sanguineo'       => trim((string)($fila['grupo_sanguineo'] ?? '')),
            'grupo_etnico'          => trim((string)($fila['grupo_etnico'] ?? '')),
            'tipo_discapacidad'     => trim((string)($fila['tipo_discapacidad'] ?? '')),
            'tipo_escolaridad'      => trim((string)($fila['tipo_escolaridad'] ?? '')),
            'ocupacion'             => trim((string)($fila['ocupacion'] ?? '')),
            'eps_nombre'            => trim((string)($fila['eps_nombre'] ?? '')) ?: 'Particular / Sin EPS',
            'numero_celular'        => $celular,
            'email'                 => trim((string)($fila['email'] ?? '')),
            'direccion_residencia'  => trim((string)($fila['direccion_residencia'] ?? '')),
            'ciudad_residencia'     => trim((string)($fila['ciudad_residencia'] ?? '')),
            'barrio'                => trim((string)($fila['barrio'] ?? '')),
            'zona'                  => trim((string)($fila['zona'] ?? '')),
            'sede_atencion'         => trim((string)($fila['sede_atencion'] ?? '')),
            'tipo_afiliado'         => trim((string)($fila['tipo_afiliado'] ?? '')),
            'nivel_socioeconomico'  => trim((string)($fila['nivel_socioeconomico'] ?? '')),
            'estrato_socioeconomico'=> trim((string)($fila['estrato_socioeconomico'] ?? '')),
            'ips_primaria'          => trim((string)($fila['ips_primaria'] ?? '')),
            'ips_remite'            => trim((string)($fila['ips_remite'] ?? '')),
            'grupo_poblacional'     => trim((string)($fila['grupo_poblacional'] ?? '')),
            'ciudad_expedicion'     => trim((string)($fila['ciudad_expedicion'] ?? '')),
            'telefono'              => $celular,
        ];
    }
}
