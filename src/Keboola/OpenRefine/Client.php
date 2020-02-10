<?php

declare(strict_types=1);

namespace Keboola\OpenRefine;

use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use Keboola\Csv\CsvFile;
use Keboola\Temp\Temp;

class Client
{
    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * @var Temp
     */
    protected $temp;

    /**
     * the CSRF token
     *
     * @var string
     */
    protected $csrfToken;

    /**
     * OpenRefine server version
     *
     * @var null|string
     */
    private static $version = null;

    /**
     * Minimum version of OpenRefine for which the CSRF token must be used
     */
    protected const MIN_VERSION_FOR_CSRF = '3.3';

    /**
     * Client constructor.
     *
     * @param string $host
     * @param string $port
     * @param Temp|null $temp
     */
    public function __construct(string $host = 'localhost', string $port = '3333', ?Temp $temp = null)
    {
        $this->client = new \GuzzleHttp\Client([
            'base_uri' => 'http://' . $host . ':' . $port . '/command/core/',
        ]);
        if (!$temp) {
            $temp = new Temp();
        }
        $this->temp = $temp;
    }

    public function createProject(CsvFile $file, string $name): string
    {
        if ($file->getColumnsCount() === 0) {
            throw new Exception('Empty file');
        }

        try {
            $response = $this->post(
                'create-project-from-upload',
                [
                    'multipart' => [
                        [
                            'name' => 'project-file',
                            'contents' => fopen($file->getPathname(), 'r'),
                        ],
                        [
                            'name' => 'project-name',
                            'contents' => $name,
                        ],
                    ],
                    'allow_redirects' => false,
                ]
            );
        } catch (ServerException $e) {
            $response = $e->getResponse();
            if ($response && $response->getReasonPhrase() === 'GC overhead limit exceeded') {
                throw new Exception('OpenRefine is out of memory. Data set too large.');
            }
            throw $e;
        }

        if ($response->getStatusCode() !== 302) {
            throw new Exception('Cannot create project: {$response->getStatusCode()}');
        }
        $url = $response->getHeader('Location')[0];
        $projectId = substr($url, strrpos($url, '=') + 1);
        return $projectId;
    }

    /**
     * @param string $projectId
     * @param array $operations
     * @throws Exception
     */
    public function applyOperations(string $projectId, array $operations): void
    {
        try {
            $response = $this->post(
                'apply-operations',
                [
                    'form_params' => [
                        'project' => $projectId,
                        'operations' => json_encode($operations),
                    ],
                ]
            );
        } catch (ServerException $e) {
            $response = $e->getResponse();
            if ($response && $response->getReasonPhrase() === 'GC overhead limit exceeded') {
                throw new Exception('OpenRefine is out of memory. Data set too large.');
            }
            throw $e;
        }

        if ($response->getStatusCode() !== 200) {
            // Actually never managed to get here
            throw new Exception('Cannot apply operations: ({$response->getStatusCode()}) {$response->getBody()}');
        }
        if ($this->isResponseError($response)) {
            throw new Exception('Cannot apply operations: {$this->getResponseError($response)}');
        }
    }

    public function exportRowsToCsv(string $projectId): CsvFile
    {
        $response = $this->post('export-rows', [
            'form_params' => [
                'project' => $projectId,
                'format' => 'csv',
            ],
        ]);
        if ($response->getStatusCode() !== 200) {
            throw new Exception('Cannot export rows: ({$response->getStatusCode()}) {$response->getBody()}');
        }

        $fileName = $this->temp->createFile('data.csv', true)->getPathname();
        $handle = fopen($fileName, 'w');
        if (!$handle) {
            throw new Exception('Cannot open file ' . $fileName . ' for writing.');
        }
        $buffer = $response->getBody()->read(1000);
        while ($buffer !== '') {
            fwrite($handle, $buffer);
            $buffer = $response->getBody()->read(1000);
        }
        fclose($handle);
        return new CsvFile($fileName);
    }

    /**
     * @param string $projectId
     * @return mixed
     * @throws Exception
     */
    public function getProjectMetadata(string $projectId)
    {
        $response = $this->client->request('GET', 'get-project-metadata?project={$projectId}');
        if ($this->isResponseError($response)) {
            throw new Exception('Project not found: {$this->getResponseError($response)}');
        }
        $decodedResponse = json_decode($response->getBody()->__toString(), true);
        return $decodedResponse;
    }

    public function deleteProject(string $projectId): void
    {
        $response = $this->post('delete-project', [
            'form_params' => [
                'project' => $projectId,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            // Actually never managed to get here
            throw new Exception('Cannot delete project: ({$response->getStatusCode()}) {$response->getBody()}');
        }
        if ($this->isResponseError($response)) {
            throw new Exception('Cannot delete project: {$this->getResponseError($response)}');
        }
    }

    protected function isResponseError(Response $response): bool
    {
        $decodedResponse = json_decode($response->getBody()->__toString(), true);
        if (isset($decodedResponse['status']) && $decodedResponse['status'] === 'error' ||
            isset($decodedResponse['code']) && $decodedResponse['code'] === 'error'
        ) {
            return true;
        }
        return false;
    }

    /**
     * @param Response $response
     * @return mixed
     */
    protected function getResponseError(Response $response)
    {
        $decodedResponse = json_decode($response->getBody()->__toString(), true);
        if (isset($decodedResponse['status'])) {
            return $decodedResponse['status'];
        }
        if (isset($decodedResponse['code'])) {
            return $decodedResponse['code'];
        }
    }

    /**
     * Sets the OpenRefine version calling the get-version endpoint
     *
     * @return void
     */
    private function setVersion(): void
    {
        if (is_null(self::$version)) {
            self::$version = '0.0';
            $response = $this->client->request('GET', 'get-version');
            if (!$this->isResponseError($response)) {
                $decodedResponse = json_decode($response->getBody()->__toString(), true);
                if (array_key_exists('version', $decodedResponse)) {
                    self::$version = $decodedResponse['version'];
                }
            }
        }
    }

    /**
     * Gets the OpenRefine version
     *
     * @return string
     */
    protected function getVersion(): string
    {
        if (is_null(self::$version)) {
            $this->setVersion();
        }
        return self::$version;
    }

    /**
     * Gets the CSRF token
     *
     * @return string
     */
    protected function getCsrfToken(): string
    {
        try {
            $this->setCsrfToken();
        } catch (Exception $e) {
            $this->csrfToken = '';
        }
        return $this->csrfToken;
    }

    /**
     * Sets the CSRF token calling the get-csrf-token endpoint
     *
     * @return void
     */
    protected function setCsrfToken(): void
    {
        $token = '';
        $error = false;
        if (is_null($this->csrfToken) && version_compare(self::getVersion(), self::MIN_VERSION_FOR_CSRF, '>=')) {
            $response = $this->client->request('GET', 'get-csrf-token');
            if (!$this->isResponseError($response)) {
                $decodedResponse = json_decode($response->getBody()->__toString(), true);
                if (array_key_exists('token', $decodedResponse)) {
                    $token = $decodedResponse['token'];
                } else {
                    $error = true;
                }
            } else {
                $error = true;
            }
        }
        if ($error) {
            throw new Exception('Cannot GET the CSRF token');
        }
        $this->csrfToken = $token;
    }

    /**
     * Does the post request using the client and setting the CSRF token if needed
     *
     * @param string $endpoint
     * @param array $params
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function post(string $endpoint, array $params = []): \Psr\Http\Message\ResponseInterface
    {
        if (version_compare(self::getVersion(), self::MIN_VERSION_FOR_CSRF, '>=')) {
            $this->csrfToken = $this->getCsrfToken();
            if ($this->csrfToken !== '') {
                if (stristr($endpoint, 'create') !== false) {
                    $endpoint .= '?csrf_token='.$this->csrfToken;
                } else if (array_key_exists('multipart', $params)) {
                    array_push($params['multipart'], [
                        'name' => 'csrf_token',
                        'contents' => $this->csrfToken,
                    ]);
                } else if (array_key_exists('form_params', $params)) {
                    $params['form_params']['csrf_token'] = $this->csrfToken;
                } else {
                    $params['csrf_token'] = $this->csrfToken;
                }
            }
            // The CSRF token is a one timer, forget it to get a new one
            $this->csrfToken = null;
        }
        return $this->client->request('POST', $endpoint, $params);
    }
}
