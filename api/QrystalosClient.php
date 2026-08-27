<?php
// api/QrystalosClient.php

class QrystalosClient {
    private $config;

    public function __construct(array $config) {
        $this->config = $config;
    }

    public function enviarPaciente(array $parametros, bool $esEdicion = false): array {
        $url = rtrim($this->config['base_url'], '/') . '/api/json/';
        $metodo = $esEdicion ? 'EDITAR' : 'INSERTAR';

        $payload = [
            'MODELO' => 'SISPAM', // Según la guía que nos dio qrystalos, el modelo fijo es SISPAM
            'METODO' => $metodo,
            'USUARIO' => $this->config['usuario_auditoria'],
            'PARAMETROS' => $parametros
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($this->config['auth_user'] . ':' . $this->config['auth_pass'])
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            return ['success' => false, 'error' => "Error de red cURL: " . $curlError];
        }

        if ($httpCode === 401) return ['success' => false, 'error' => 'Error 401: Credenciales Basic Auth inválidas.'];
        if ($httpCode === 403) return ['success' => false, 'error' => 'Error 403: Usuario sin permisos para el modelo.'];
        if ($httpCode >= 500) return ['success' => false, 'error' => "Error del servidor Qrystalos (HTTP $httpCode)."];

        $data = json_decode($responseBody, true);

        if (!isset($data['result']['recordsets'][0][0])) {
            return ['success' => false, 'error' => 'Respuesta JSON no reconocida.'];
        }

        $mainRecord = $data['result']['recordsets'][0][0];
        $isOk = ($mainRecord['OK'] ?? '') === 'OK';

        if ($isOk) {
            return [
                'success' => true,
                'consecutivo' => $mainRecord['CONSECUTIVO'] ?? null,
                'accion' => $mainRecord['ACCION'] ?? 'Desconocida',
                'mensaje' => $mainRecord['MENSAJE'] ?? 'Procesado'
            ];
        } else {
            $errorMsg = 'Error de negocio desconocido';
            if (isset($data['result']['recordsets'][1])) {
                foreach ($data['result']['recordsets'][1] as $err) {
                    if (isset($err['ERROR'])) {
                        $errorMsg = $err['ERROR'];
                        break;
                    }
                }
            }
            return ['success' => false, 'error' => $errorMsg];
        }
    }
}