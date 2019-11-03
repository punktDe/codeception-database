<?php

namespace PunktDe\Codeception\Database\Module;

/*
 * This file is part of the PunktDe\Codeception.Database package.
 *
 * This package is open source software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */
use Behat\Gherkin\Node\TableNode;
use Codeception\Module\Db;
use Neos\Utility\Arrays;
use Symfony\Component\Yaml\Yaml;
use Codeception\Configuration;
use Neos\Utility\Files;

class Database extends Db
{

    /**
     * @param string $dataset
     */
    public function importDataset(string $dataset): void
    {
        $fileContents = Files::getFileContents(Files::concatenatePaths([Configuration::testsDir(), $dataset]));

        $datasetArray = Yaml::parse($fileContents);

        if (is_array($datasetArray)) {
            foreach ($datasetArray as $tableName => $listOfItems) {
                $this->driver->deleteQueryByCriteria($tableName, []);

                if (is_array($listOfItems)) {
                    foreach ($listOfItems as $datarow) {
                        $this->haveInDatabase($tableName, $datarow);
                    }
                }
            }
        }
    }

    /**
     * @param string $table
     * @param TableNode $tableNode
     */
    public function databaseTableShouldContainTable(string $table, TableNode $tableNode): void
    {
        $tableRows = $tableNode->getRows();
        $arrayKeys = $tableRows[0];
        unset($tableRows[0]);

        foreach ($tableRows as $singleRow) {
            $singleRow = array_combine($arrayKeys, $singleRow);
            $this->seeInDatabase($table, $singleRow);
        }
    }

    /**
     * @param string $query
     * @param TableNode $jsonContent
     * @throws \Exception
     */
    public function databaseQueryReturnsFieldWithJson(string $query, TableNode $jsonContent): void
    {
        $pdoStatement = $this->driver->executeQuery($query, []);
        /** @var $pdoStatement \PDOStatement */

        $this->assertEquals(0, $pdoStatement->errorCode(), sprintf('Execution of query "%s" failed with error "%s" (%d)', $query, implode("\n", $pdoStatement->errorInfo()), $pdoStatement->errorCode()));

        $result = $pdoStatement->fetchAll(\PDO::FETCH_NUM);
        $this->assertEquals(1, count($result), sprintf('Query "%s" returned more than one result row', $query));

        $dataRow = $result[0];
        $this->assertEquals(1, count($dataRow), sprintf('Query "%s" returned more than one result field in data row', $query));

        $data = json_decode($dataRow[0], true);
        if ($data === null) {
            throw new \Exception(sprintf('The result of the query "%s" could not be parsed to JSON', $query), 1432278325);
        }

        $jsonRows = $jsonContent->getRows();
        foreach ($jsonRows as $singleRow) {
            $dataContent = Arrays::getValueByPath($data, $singleRow[0]);
            $this->assertEquals(json_decode($singleRow[1]), $dataContent, sprintf('Failed asserting that "%s" matches expected "%s" for entry "%s"', $dataContent, $singleRow[1], $singleRow[0]));
        }
    }

}
