<?php
// services/QrystalosSyncService.php
require_once __DIR__ . '/../api/QrystalosClient.php';

class QrystalosSyncService {
    private $client;
    private $config;

    public function __construct() {
        $this->config = require __DIR__ . '/../config/qrystalos.php';
        $this->client = new QrystalosClient($this->config);
    }

    public function sincronizarPaciente(array $datosCsv, ?string $idAfiliadoExistente = null): array {
        $payload = [
            'TIPO_DOC' => strtoupper($datosCsv['tipo_documento'] ?? 'CC'),
            'DOCIDAFILIADO' => preg_replace('/[^\dA-Z]/', '', $datosCsv['numero_documento'] ?? ''),
            'FNACIMIENTO' => $datosCsv['fecha_nacimiento'] ?? '1990-01-01',
            'PAPELLIDO' => strtoupper($datosCsv['primer_apellido'] ?? 'APELLIDO'),
            'PNOMBRE' => strtoupper($datosCsv['primer_nombre'] ?? 'NOMBRE'),
            'SEXO' => $datosCsv['sexo'] ?? 'Masculino',
            
            // Campos faltantes en el CSV, se usan defaults del config para evitar errores inesperados 

            'ESTADO_CIVIL' => $this->config['defaults']['estado_civil'],
            'GRUPOPOB' => $this->config['defaults']['grupo_pob'],
            'GRUPOETNICO' => $this->config['defaults']['grupo_etnico'],
            'TIPODISCAPACIDAD' => $this->config['defaults']['tipo_discapacidad'],
            'IDESCOLARIDAD' => $this->config['defaults']['escolaridad'],
            
            'DIRECCION' => strtoupper($datosCsv['direccion_residencia'] ?? 'DIRECCION NO ESPECIFICADA'),
            'CELULAR' => preg_replace('/[^\d]/', '', $datosCsv['numero_celular'] ?? '3000000000'),
            
            // Email es obligatorio. Si no viene en el CSV, generamos uno temporal único

            'EMAIL' => !empty($datosCsv['email']) ? $datosCsv['email'] : 'paciente_' . $datosCsv['numero_documento'] . '@temp.sispam.local',
            
            // Catálogos (se actualizarán con datos reales del onboarding que vendra en qrystalos)
            
            'CIUDAD' => $this->config['catalogs']['ciudad'],
            'ZONA' => $this->config['defaults']['zona'],
            'IDBARRIO' => $this->config['catalogs']['id_barrio'],
            'IDADMINISTRADORA' => $this->config['catalogs']['id_administradora'],
            'IDPLAN' => $this->config['catalogs']['id_plan'],
            'NIVELSOCIOEC' => $this->config['defaults']['nivel_socioec'],
            'TIPOUSUARIO' => $this->config['defaults']['tipo_usuario'],
            'IDSEDE' => $this->config['catalogs']['id_sede'],
            'ESTADO' => $this->config['defaults']['estado'],
            'PROCEDENCIA' => $this->config['defaults']['procedencia']
        ];

        if ($idAfiliadoExistente) {
            $payload['IDAFILIADO'] = $idAfiliadoExistente;
        }

        return $this->client->enviarPaciente($payload, !is_null($idAfiliadoExistente));
    }
}