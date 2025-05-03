<?php
// src/ServiceLayerClient.php

namespace App;

use App\Exceptions\{
    AuthenticationException,
    UnauthorizedException,
    ForbiddenException,
    NotFoundException,
    AlreadyCanceledException,
    BusinessException,
    ServiceLayerException
};

/**
 * Cliente para autenticar e cancelar JournalEntries (LCM) via Service Layer do SAP B1 HANA.
 */
class ServiceLayerClient
{
    private string $baseUrl;
    private string $companyDB;
    private string $username;
    private string $password;
    private string $logFile;

    /**
     * @param array  $slConfig Configuração do SL, com chaves:
     *                         - host (ex: "teste.teste.com.br:50000")
     *                         - version (ex: "v2")
     *                         - companyDB
     *                         - username
     *                         - password
     * @param string $logFile   Caminho do arquivo de log
     */
    public function __construct(array $slConfig, string $logFile)
    {
        $this->baseUrl   = "https://{$slConfig['host']}/b1s/{$slConfig['version']}";
        $this->companyDB = $slConfig['companyDB'];
        $this->username  = $slConfig['username'];
        $this->password  = $slConfig['password'];
        $this->logFile   = $logFile;
    }

    /** Escreve mensagem no log */
    private function writeLog(string $msg): void
    {
        $time = date('Y-m-d H:i:s');
        error_log("[{$time}] {$msg}\n", 3, $this->logFile);
    }

    /**
     * Autentica no Service Layer e retorna o SessionId.
     *
     * @return string SessionId válido
     * @throws AuthenticationException Se falhar o login
     */
    public function login(): string
    {
        $url     = "{$this->baseUrl}/Login";
        $payload = json_encode([
            'CompanyDB' => $this->companyDB,
            'UserName'  => $this->username,
            'Password'  => $this->password,
        ]);

        $this->writeLog("LOGIN → URL: {$url}");
        $this->writeLog("LOGIN → Payload: {$payload}");

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FAILONERROR    => false,
            CURLOPT_TIMEOUT        => 30,               // timeout de 30s
        ]);

        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->writeLog("LOGIN ← HTTP Code: {$httpCode}");
        $this->writeLog("LOGIN ← Response: {$resp}");

        if ($httpCode !== 200 || !$resp) {
            throw new AuthenticationException("Login falhou (HTTP {$httpCode})");
        }

        $data = json_decode($resp, true);
        if (empty($data['SessionId'])) {
            throw new AuthenticationException("Nenhum SessionId retornado pelo SL.");
        }

        return $data['SessionId'];
    }

    /**
     * Cancela um  LCM no Service Layer.
     *
     * @param string $sessionId SessionId válido
     * @param int    $docEntry  Número do DocEntry
     * @return void
     * @throws AlreadyCanceledException Se já tiver sido cancelado
     * @throws UnauthorizedException    Se session inválida
     * @throws ForbiddenException       Se acesso negado
     * @throws NotFoundException        Se DocEntry não existir
     * @throws BusinessException        Para erros de negócio
     * @throws ServiceLayerException    Para outros erros
     */
    public function cancel(string $sessionId, int $docEntry): void
    {
        $url = "{$this->baseUrl}/JournalEntries({$docEntry})/Cancel";
        $this->writeLog("CANCEL → URL: {$url}");
        $this->writeLog("CANCEL → SessionId: {$sessionId}");

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Cookie: B1SESSION={$sessionId}"
            ],
            CURLOPT_POSTFIELDS     => '{}',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FAILONERROR    => false,
            CURLOPT_TIMEOUT        => 30,               // timeout de 30s
        ]);

        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->writeLog("CANCEL ← HTTP Code: {$httpCode}");
        $this->writeLog("CANCEL ← Response: {$resp}");

        // Sucesso: 200 OK, 201 Created ou 204 No Content
        if (in_array($httpCode, [200, 201, 204], true)) {
            return;
        }

        // Tenta parsear o JSON de erro
        $body = json_decode($resp, true) ?: [];

        switch ($httpCode) {
            case 400:
                // Erro de negócio
                $msg = $body['error']['message']['value']
                     ?? $body['error']['message']
                     ?? $body['message']
                     ?? 'Bad Request';
                if (stripos($msg, 'already been canceled') !== false) {
                    throw new AlreadyCanceledException($msg);
                }
                throw new BusinessException($msg);

            case 401:
                throw new UnauthorizedException('Sessão inválida ou expirada');

            case 403:
                throw new ForbiddenException('Acesso negado pelo Service Layer');

            case 404:
                throw new NotFoundException("LCM {$docEntry} não encontrado");

            default:
                $msg = $body['message'] ?? "Erro HTTP {$httpCode}";
                throw new ServiceLayerException($msg);
        }
    }
}
