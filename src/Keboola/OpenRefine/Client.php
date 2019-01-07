<?php
/**
 * Created by PhpStorm.
 * User: ondra
 * Date: 21/09/16
 * Time: 16:45
 */

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
     * @param int $port
     * @param Temp|null $temp
     */
    public function __construct($host = "localhost", $port = 3333, $temp = null)
    {
        $this->client = new \GuzzleHttp\Client([
            "base_uri" => "http://" . $host . ":" . $port . "/command/core/"
        ]);
        if (!$temp) {
            $temp = new Temp();
        }
        $this->temp = $temp;
    }

    /**
     * @param CsvFile $file
     * @param string $name
     * @return string
     * @throws Exception
     */
    public function createProject(CsvFile $file, $name)
    {
        if ($file->getColumnsCount() === 0) {
            throw new Exception("Empty file");
        }

        try {
            $response = $this->client->request(
                "POST",
                "create-project-from-upload",
                [
                    "multipart" => [
                        [
                            "name" => "project-file",
                            "contents" => fopen($file->getPathname(), "r"),
                        ],
                        [
                            "name" => "project-name",
                            "contents" => $name
                        ]
                    ],
                    "allow_redirects" => false
                ]
            );
        } catch (ServerException $e) {
            $response = $e->getResponse();
            if ($response && $response->getReasonPhrase() == 'GC overhead limit exceeded') {
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
    public function applyOperations($projectId, $operations)
    {
        try {
            $response = $this->client->request(
                "POST",
                "apply-operations",
                [
                    "form_params" => [
                        "project" => $projectId,
                        "operations" => json_encode($operations)
                    ]
                ]
            );
        } catch (ServerException $e) {
            $response = $e->getResponse();
            if ($response && $response->getReasonPhrase() == 'GC overhead limit exceeded') {
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

    /**
     * @param string $projectId
     * @return CsvFile
     * @throws Exception
     */
    public function exportRowsToCsv($projectId)
    {
        $response = $this->client->request("POST", "export-rows", [
            "form_params" => [
                "project" => $projectId,
                "format" => "csv"
            ]
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
        while ($buffer != '') {
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
    public function getProjectMetadata($projectId)
    {
        $response = $this->client->request("GET", "get-project-metadata?project={$projectId}");
        if ($this->isResponseError($response)) {
            throw new Exception("Project not found: {$this->getResponseError($response)}");
        }
        $decodedResponse = json_decode($response->getBody()->__toString(), true);
        return $decodedResponse;
    }

    /**
     * @param string $projectId
     * @throws Exception
     */
    public function deleteProject($projectId)
    {
        $response = $this->client->request("POST", "delete-project", [
            "form_params" => [
                "project" => $projectId
            ]
        ]);

        if ($response->getStatusCode() !== 200) {
            // Actually never managed to get here
            throw new Exception("Cannot delete project: ({$response->getStatusCode()}) {$response->getBody()}");
        }
        if ($this->isResponseError($response)) {
            throw new Exception("Cannot delete project: {$this->getResponseError($response)}");
        }
    }

    /**
     * @param Response $response
     * @return bool
     */
    protected function isResponseError(Response $response)
    {
        $decodedResponse = json_decode($response->getBody()->__toString(), true);
        if (isset($decodedResponse["status"]) && $decodedResponse["status"] == "error" ||
            isset($decodedResponse["code"]) && $decodedResponse["code"] == "error"
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
