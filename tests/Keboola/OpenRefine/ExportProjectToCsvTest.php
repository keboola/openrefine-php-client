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

class ExportProjecetToCsvTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateProjectSuccess()
    {
        $client = new Client(getenv("OPENREFINE_HOST"), getenv("OPENREFINE_PORT"));
        $temp = new Temp();
        $fileInfo = $temp->createFile("file.csv");
        $csv = new CsvFile($fileInfo->getPathname());
        $csv->writeRow(["col1", "col2"]);
        $csv->writeRow(["A", "B"]);
        $projectId = $client->createProject($csv, "test");
        $file = $client->exportRowsToCsv($projectId);
        $this->assertInstanceOf(CsvFile::class, $file);
    }
}
