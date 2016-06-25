<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SiteMigration\Test\Unit;

use Piwik\Plugins\SiteMigration\Helper\DBHelper;

/**
 * @group SiteMigration
 */
class DBHelperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var DBHelper
     */
    private $dbHelper;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $adapter;

    private $config;

    public function setUp()
    {
        parent::setUp();

        $this->reset();
    }

    protected function reset($translations = array())
    {
        $this->adapter = $this->getMock(
            'Zend_Db_Adapter_Pdo_Mysql',
            array(),
            array(),
            '',
            false
        );
        $this->config  = array(
            'dbname'        => 'piwik__test',
            'host'          => 'example.com',
            'username'      => 'example',
            'password'      => 'foobar',
            'tables_prefix' => 'piwik_',
        );

        $this->dbHelper = new DBHelper($this->adapter, $this->config);
    }

    public function test_getAdapter()
    {
        $this->assertEquals($this->adapter, $this->dbHelper->getAdapter());
    }

    public function test_getConfig()
    {
        $this->assertEquals($this->config, $this->dbHelper->getConfig());
    }

    public function test_getInsertSQL()
    {
        $values = array('foo' => 'bar', 'dummy' => 'dummy');
        $sql    = "INSERT INTO piwik_table(`foo`, `dummy`) VALUES ('bar', 'dummy')";

        $this->adapter->expects($this->exactly(2))->method('quote')->will(
            $this->returnCallback(
                function ($text) {
                    return "'" . $text . "'";
                }
            )
        );

        $this->assertEquals($sql, $this->dbHelper->getInsertSQL('table', $values));
    }

    public function test_executeInsert()
    {
        $this->adapter->expects($this->once())->method('insert')->with($this->dbHelper->prefixTable('table'), $this->anything());
        $this->dbHelper->executeInsert('table', array());
    }

    public function test_getDBName()
    {
        $this->assertEquals('piwik__test', $this->dbHelper->getDBName());
    }

    public function test_acquireLock_positive()
    {
        $this->adapter->expects($this->once())->method('fetchOne')->will($this->returnValue('1'));

        $this->assertEquals(true, $this->dbHelper->acquireLock('dummy'));
    }

    public function test_acquireLock_negative()
    {
        $this->adapter->expects($this->exactly(10))->method('fetchOne')->will($this->returnValue('0'));

        $this->assertEquals(false, $this->dbHelper->acquireLock('dummy', 10));
    }

    public function test_releaseLock()
    {
        $this->adapter->expects($this->once())->method('fetchOne')->will($this->returnValue('1'));

        $this->assertEquals(true, $this->dbHelper->releaseLock('dummy'));
    }

    public function test_lastInsertId()
    {
        $this->adapter->expects($this->once())->method('lastInsertId')->with('table', null)->will($this->returnValue(2));

        $this->assertEquals(2, $this->dbHelper->lastInsertId('table'));
    }

    /**
     * @test
     */
    public function test_it_flushes_correctly()
    {
        $this->dbHelper->insert('test1', array('k1' => 'v1', 'k2' => 2));
        $this->dbHelper->insert('test1', array('k1' => 'v3', 'k2' => 4));
        $this->dbHelper->insert('test2', array('k3' => 'v5', 'k4' => 6));

        $this->adapter->expects($this->exactly(6))->method('quote')->willReturnCallback(function ($arg) {
            return "'" . $arg . "'";
        });
        $this->adapter->expects($this->exactly(2))->method('query')->withConsecutive(
            array(
                "INSERT INTO piwik_test1 (`k1`, `k2`) VALUES ('v1', '2'), ('v3', '4')"
            ),
            array(
                "INSERT INTO piwik_test2 (`k3`, `k4`) VALUES ('v5', '6')"
            )
        );

        $this->dbHelper->flushInserts();
    }
}