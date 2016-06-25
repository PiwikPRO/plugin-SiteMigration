<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SiteMigration\Test\Integration\Command;

use Piwik\Db;
use Piwik\Plugins\SiteMigration\Helper\DBHelper;
use Piwik\Plugins\SiteMigration\Test\Fixture\AbstractTableFixture;
use Piwik\Plugins\SiteMigration\Test\Fixture\MigratedSimpleData;
use Piwik\Plugins\SiteMigration\Test\Fixture\TrackedData;
use Piwik\Tests\Framework\TestCase\ConsoleCommandTestCase;

/**
 * @group SiteMigration
 * @group MigrateSiteTest
 * @group Plugins
 */
class MigrateSiteTest extends ConsoleCommandTestCase
{
    public static $fixture = null;

    public static $migrateSimpleFixture = null;

    public function test_UnexistingWebsite()
    {
        $result = $this->applicationTester->run(array(
            'command' => 'migration:site',
            'idSite' => 22
        ));

        $this->assertEquals(1, $result, $this->getCommandDisplayOutputErrorMessage());
    }

    public function test_MigrateSimple()
    {
        $result = $this->applicationTester->run(array(
            'command' => 'migration:site',
            'idSite' => 1
        ));

        $this->assertEquals(0, $result, $this->getCommandDisplayOutputErrorMessage());

        $this->_compareWithDB(self::$migrateSimpleFixture);
    }

    protected function _compareWithDB(AbstractTableFixture $resultFixture){
        foreach ($resultFixture->data as $tableName => $table){
            $this->_checkTable($tableName, $table);
        }
    }

    protected function _checkTable($tableName, $table){
        $rows = $table['data'];

        $db = Db::get();
        $dbHelper = new DBHelper($db, Db::getDatabaseConfig());

        $rowCount  = $db->fetchOne('SELECT COUNT(*) FROM ' . $dbHelper->prefixTable($tableName));
        $this->assertEquals(count($rows), $rowCount, sprintf('The expected row numbers for the table %s is %d and not %d.', $tableName, $rowCount, count($rows)));

        foreach ($rows as $row){
            $query = "SELECT * FROM " . $dbHelper->prefixTable($tableName) . ' WHERE';

            $isFirstCondition = true;
            foreach($table['primary_key'] as $columnName) {
                if (!$isFirstCondition) {
                    $query .= ' AND';
                }

                $rowValue = $row[$columnName];

                switch(gettype($rowValue)){
                    case "string":
                    case "integer":
                        $query .= ' `' . $columnName . '` = \''  . $rowValue . '\'';
                        break;
                    case "NULL":
                        $query .= ' `' . $columnName . '` IS NULL';
                        break;
                }

                $isFirstCondition = false;
            }

            $dbEntry = $db->fetchRow($query);

            $this->assertEquals($row, $dbEntry);
        }
    }
}

MigrateSiteTest::$fixture = new TrackedData();
MigrateSiteTest::$migrateSimpleFixture = new MigratedSimpleData();