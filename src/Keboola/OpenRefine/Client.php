<?php
/**
 * Created by PhpStorm.
 * User: ondra
 * Date: 21/09/16
 * Time: 16:45
 */

namespace Keboola\OpenRefine;

use Keboola\Csv\CsvFile;

class Client
{
    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * Client constructor.
     *
     * @param string $host
     * @param int $port
     */
    public function __construct($host = "localhost", $port = 3333)
    {
        $this->client = new \GuzzleHttp\Client([
            "base_uri" => "http://" . $host . ":" . $port . "/command/core/"
        ]);
    }

    /**
     * @param CsvFile $file
     * @param $name
     * @return string
     * @throws Exception
     */
    public function createProject(CsvFile $file, $name)
    {
        if ($file->getColumnsCount() === 0) {
            throw new Exception("Empty file");
        }

        $response = $this->client->request("POST", "create-project-from-upload", [
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
        ]);

        if ($response->getStatusCode() !== 302) {
            throw new Exception("Cannot create project: {$response->getStatusCode()}");
        }
        $url = $response->getHeader("Location")[0];
        $projectId = substr($url, strrpos($url, "=") + 1);
        return $projectId;
    }

    /**
     * @param $projectId
     * @param $operations
     * @throws Exception
     */
    public function applyOperations($projectId, $operations)
    {
        $response = $this->client->request("POST", "apply-operations", [
            "form_params" => [
                "project" => $projectId,
                "operations" => json_encode($operations)
            ]
        ]);

        if ($response->getStatusCode() !== 200) {
            // Actually never managed to get here
            throw new Exception("Cannot apply operations: ({$response->getStatusCode()}) {$response->getBody()}");
        }
        $decodedResponse = json_decode($response->getBody()->__toString(), true);
        if ($decodedResponse["status"] == "error") {
            throw new Exception("Cannot apply operations: {$decodedResponse["message"]}");
        }
    }

    /**
     * @param $projectId
     * @param $fileName
     * @throws Exception
     */
    public function exportRowsToCsv($projectId, $fileName)
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

        $handle = fopen($fileName, "w");
        $buffer = $response->getBody()->read(1000);
        while ($buffer != '') {
            fwrite($handle, $buffer);
            $buffer = $response->getBody()->read(1000);
        }
        fclose($handle);
    }

    /**
     * @param $projectId
     * @return mixed
     * @throws Exception
     */
    public function getProjectMetadata($projectId)
    {
        $response = $this->client->request("GET", "get-project-metadata?project={$projectId}");
        $decodedResponse = json_decode($response->getBody()->__toString(), true);
        if ($decodedResponse["status"] == "error") {
            throw new Exception("Project not found: {$decodedResponse["message"]}");
        }
        return $decodedResponse;
    }

    /**
     * @param $projectId
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
        $decodedResponse = json_decode($response->getBody()->__toString(), true);
        if ($decodedResponse["status"] == "error") {
            throw new Exception("Cannot delete project: {$decodedResponse["message"]}");
        }
    }
}
