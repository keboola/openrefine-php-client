<?php

declare(strict_types=1);

namespace Keboola\OpenRefine;

use Keboola\Csv\CsvFile;
use Keboola\Temp\Temp;

class CreateProjectTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateProjectSuccess(): void
    {
        $client = new Client(getenv('OPENREFINE_HOST'), getenv('OPENREFINE_PORT'));
        $temp = new Temp();
        $fileInfo = $temp->createFile('file.csv');
        $csv = new CsvFile($fileInfo->getPathname());
        $csv->writeRow(['col1', 'col2']);
        $csv->writeRow(['A', 'B']);
        $projectId = $client->createProject($csv, 'test');
        $this->assertNotNull($projectId, 'Did not return project id');
        $this->assertRegExp('/^[0-9]*$/', $projectId);
        $this->assertGreaterThan(0, $projectId);
        $this->assertEquals('test', $client->getProjectMetadata($projectId)['name']);
        $outCsv = $client->exportRowsToCsv($projectId);
        $this->assertEquals('col1,col2\nA,B\n', file_get_contents($outCsv->getPathname()));
    }

    public function testsCreateProjectEmptyFile(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Empty file');
        $client = new Client(getenv('OPENREFINE_HOST'), getenv('OPENREFINE_PORT'));
        $temp = new Temp();
        $fileInfo = $temp->createFile('empty_file.csv');
        $csv = new CsvFile($fileInfo->getPathname());
        $client->createProject($csv, 'test');
    }
}
