<?php


namespace Piwik\Plugins\SiteMigrator\Test;

use Piwik\Plugins\SiteMigrator\Helper\DBHelper;

/**
 * @group SiteMigrator
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
}