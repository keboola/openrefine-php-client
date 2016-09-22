<?php
/**
 * Created by PhpStorm.
 * User: ondra
 * Date: 21/09/16
 * Time: 17:22
 */

namespace Keboola\OpenRefine;

use Keboola\Csv\CsvFile;
use Keboola\Temp\Temp;

class ApplyOperationsTest extends \PHPUnit_Framework_TestCase
{
    public function testApplyOperationsSuccess()
    {
        $client = new Client(getenv("OPENREFINE_HOST"), getenv("OPENREFINE_PORT"));
        $temp = new Temp();
        $fileInfo = $temp->createFile("file.csv");
        $csv = new CsvFile($fileInfo->getPathname());
        $csv->writeRow(["col1", "col2"]);
        $csv->writeRow(["A", "B"]);
        $projectId = $client->createProject($csv, "test");

        $operationsJSON = <<<JSON
[
    {
        "op": "core/mass-edit",
        "description": "Mass edit cells in column col2",
        "engineConfig": {
            "mode": "row-based",
            "facets": []
        },
        "columnName": "col2",
        "expression": "value",
        "edits": [
            {
                "fromBlank": false,
                "fromError": false,
                "from": [
                    "B"
                ],
                "to": "A"
            }
        ]
    }
]
JSON;

        $client->applyOperations($projectId, json_decode($operationsJSON, true));

        $outFileInfo = $temp->createFile("out.csv");
        $client->exportRowsToCsv($projectId, $outFileInfo->getPathname());
        $this->assertEquals("col1,col2\nA,A\n",file_get_contents($outFileInfo->getPathname()));
    }
}
