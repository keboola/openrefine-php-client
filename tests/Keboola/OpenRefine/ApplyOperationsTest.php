<?php

declare(strict_types=1);

namespace Keboola\OpenRefine;

use Keboola\Csv\CsvFile;
use Keboola\Temp\Temp;

class ApplyOperationsTest extends \PHPUnit_Framework_TestCase
{
    public function testApplyOperationsSuccess(): void
    {
        $client = new Client(getenv('OPENREFINE_HOST'), getenv('OPENREFINE_PORT'));
        $temp = new Temp();
        $fileInfo = $temp->createFile('file.csv');
        $csv = new CsvFile($fileInfo->getPathname());
        $csv->writeRow(['col1', 'col2']);
        $csv->writeRow(['A', 'B']);
        $projectId = $client->createProject($csv, 'test');

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
        $outCsv = $client->exportRowsToCsv($projectId);
        $this->assertEquals("col1,col2\nA,A\n", file_get_contents($outCsv->getPathname()));
    }
}
