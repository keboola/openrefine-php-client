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

class CreateProjectTest extends \PHPUnit_Framework_TestCase
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
        $this->assertNotNull($projectId, "Did not return project id");
        $this->assertRegExp("/^[0-9]*$/", $projectId);
        $this->assertGreaterThan(0, $projectId);
        $outFileInfo = $temp->createFile("out.csv");
        $client->exportRowsToCsv($projectId, $outFileInfo->getPathname());
        $this->assertEquals("\"col1\",\"col2\"\n\"A\",\"B\"\n",file_get_contents($fileInfo = $temp->createFile("file.csv")));
    }

    public function testsCreateProjectEmptyFile()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Empty file");
        $client = new Client(getenv("OPENREFINE_HOST"), getenv("OPENREFINE_PORT"));
        $temp = new Temp();
        $fileInfo = $temp->createFile("empty_file.csv");
        $csv = new CsvFile($fileInfo->getPathname());
        $client->createProject($csv, "test");
    }
}
