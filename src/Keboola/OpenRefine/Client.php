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
     * Client constructor.
     *
     * @param string $host
     * @param string $port
     * @param Temp|null $temp
     */
    public function __construct(string $host = "localhost", string $port = "3333", ?Temp $temp = null)
    {
        $this->client = new \GuzzleHttp\Client([
            "base_uri" => "http://" . $host . ":" . $port . "/command/core/",
        ]);
        if (!$temp) {
            $temp = new Temp();
        }
        $this->temp = $temp;
    }

    public function createProject(CsvFile $file, string $name, array $options = []): string
    {
        if ($file->getColumnsCount() === 0) {
            throw new Exception("Empty file");
        }

        $multipartData = [
            [
                'name' => 'project-file',
                'contents' => fopen($file->getPathname(), 'r'),
            ],
            [
                'name' => 'project-name',
                'contents' => $name,
            ],
        ];
        if (count($options) > 0) {
            array_push($multipartData, [
                'name' => 'options',
                'contents' => json_encode($options),
            ]);
        }
        try {
            $response = $this->client->request(
                "POST",
                "create-project-from-upload",
                [
                    "multipart" => $multipartData,
                    "allow_redirects" => false,
                ]
            );
        } catch (ServerException $e) {
            $response = $e->getResponse();
            if ($response && $response->getReasonPhrase() === 'GC overhead limit exceeded') {
                throw new Exception("OpenRefine is out of memory. Data set too large.");
            }
            throw $e;
        }

        if ($response->getStatusCode() !== 302) {
            throw new Exception("Cannot create project: {$response->getStatusCode()}");
        }
        $url = $response->getHeader("Location")[0];
        $projectId = substr($url, strrpos($url, "=") + 1);
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
            $response = $this->client->request(
                "POST",
                "apply-operations",
                [
                    "form_params" => [
                        "project" => $projectId,
                        "operations" => json_encode($operations),
                    ],
                ]
            );
        } catch (ServerException $e) {
            $response = $e->getResponse();
            if ($response && $response->getReasonPhrase() === 'GC overhead limit exceeded') {
                throw new Exception("OpenRefine is out of memory. Data set too large.");
            }
            throw $e;
        }

        if ($response->getStatusCode() !== 200) {
            // Actually never managed to get here
            throw new Exception("Cannot apply operations: ({$response->getStatusCode()}) {$response->getBody()}");
        }
        if ($this->isResponseError($response)) {
            throw new Exception("Cannot apply operations: {$this->getResponseError($response)}");
        }
    }

    public function exportRowsToCsv(string $projectId): CsvFile
    {
        $response = $this->client->request("POST", "export-rows", [
            "form_params" => [
                "project" => $projectId,
                "format" => "csv",
            ],
        ]);
        if ($response->getStatusCode() !== 200) {
            throw new Exception("Cannot export rows: ({$response->getStatusCode()}) {$response->getBody()}");
        }

        $fileName = $this->temp->createFile("data.csv", true)->getPathname();
        $handle = fopen($fileName, "w");
        if (!$handle) {
            throw new Exception("Cannot open file " . $fileName . " for writing.");
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
        $response = $this->client->request("GET", "get-project-metadata?project={$projectId}");
        if ($this->isResponseError($response)) {
            throw new Exception("Project not found: {$this->getResponseError($response)}");
        }
        $decodedResponse = json_decode($response->getBody()->__toString(), true);
        return $decodedResponse;
    }

    public function deleteProject(string $projectId): void
    {
        $response = $this->client->request("POST", "delete-project", [
            "form_params" => [
                "project" => $projectId,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            // Actually never managed to get here
            throw new Exception("Cannot delete project: ({$response->getStatusCode()}) {$response->getBody()}");
        }
        if ($this->isResponseError($response)) {
            throw new Exception("Cannot delete project: {$this->getResponseError($response)}");
        }
    }

    protected function isResponseError(Response $response): bool
    {
        $decodedResponse = json_decode($response->getBody()->__toString(), true);
        if (isset($decodedResponse["status"]) && $decodedResponse["status"] === "error" ||
            isset($decodedResponse["code"]) && $decodedResponse["code"] === "error"
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
        if (isset($decodedResponse["status"])) {
            return $decodedResponse["status"];
        }
        if (isset($decodedResponse["code"])) {
            return $decodedResponse["code"];
        }
    }
}
