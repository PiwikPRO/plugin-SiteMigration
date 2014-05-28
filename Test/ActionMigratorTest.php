<?php


namespace Piwik\Plugins\SiteMigrator\Test;


use Piwik\Plugins\SiteMigrator\Migrator\ActionMigrator;

/**
 * Class ActionMigratorTest
 * @package Piwik\Plugins\SiteMigrator\Test
 *
 * @group SiteMigrator
 */
class ActionMigratorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var actionMigrator
     */
    protected $actionMigrator;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $fromDbHelper;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $toDbHelper;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $idMapCollection;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $siteMap;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $actionMap;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $adapter;

    public function setUp()
    {
        parent::setUp();

        $this->resetSiteConfig();
    }

    protected function resetSiteConfig()
    {
        $this->siteMap         = $this->getMock('Piwik\Plugins\SiteMigrator\Model\IdMap', array('add', 'translate'));
        $this->actionMap       = $this->getMock('Piwik\Plugins\SiteMigrator\Model\IdMap', array('add', 'translate'));
        $this->idMapCollection = $idMapCollection = $this->getMock('Piwik\Plugins\SiteMigrator\Model\IdMapCollection', array('getSiteMap', 'getActionMap'));;
        $this->adapter         = $this->getMock('Zend_Db_Adapter_Pdo_Mysql', array('fetchRow', 'fetchAll'), array(), '', false);
        $this->fromDbHelper    = $this->getMock(
            'Piwik\Plugins\SiteMigrator\Helper\DBHelper',
            array('executeInsert', 'lastInsertId', 'getAdapter'),
            array(),
            '',
            false
        );

        $this->toDbHelper     = $this->getMock(
            'Piwik\Plugins\SiteMigrator\Helper\DBHelper',
            array('executeInsert', 'lastInsertId', 'getAdapter'),
            array(),
            '',
            false
        );

        $this->actionMigrator = new ActionMigrator($this->fromDbHelper, $this->toDbHelper, $this->idMapCollection);

    }


    public function test_ensureActionIsMigrated_actionIsMigrated()
    {

        $this->idMapCollection->expects($this->once())->method('getActionMap')->with()->will($this->returnValue($this->actionMap));
        $this->actionMap->expects($this->once())->method('translate')->with($this->equalTo(123))->will($this->returnValue(321));

        $this->actionMigrator->ensureActionIsMigrated(123);
    }

    public function test_ensureActionIsMigrated_actionIsNotMigrated()
    {
        $action = array(
            'idaction'   => 123,
            'name'       => 'Dummy action',
            'hash'       => '1239394545',
            'type'       => '4',
            'url_prefix' => null,
        );

        $actionTranslated = $action;
        unset($actionTranslated['idaction']);

        $this->idMapCollection->expects($this->exactly(2))->method('getActionMap')->with()->will($this->returnValue($this->actionMap));
        $this->actionMap->expects($this->once())->method('translate')->with($this->equalTo(123))->will($this->throwException(new \InvalidArgumentException('Nope')));
        $this->fromDbHelper->expects($this->once())->method('getAdapter')->with()->will($this->returnValue($this->adapter));

        $this->adapter->expects($this->once())->method('fetchRow')->will($this->returnValue($action));
        $this->toDbHelper->expects($this->once())->method('executeInsert')->with($this->equalTo('log_action'), $this->equalTo($actionTranslated));
        $this->toDbHelper->expects($this->once())->method('lastInsertId')->will($this->returnValue(321));

        $this->actionMap->expects($this->once())->method('add')->with($this->equalTo(123), $this->equalTo(321));

        $this->actionMigrator->ensureActionIsMigrated(123);
    }

    public function test_ensureActionIsMigrated_actionWasMigratedBefore()
    {
        $this->markTestIncomplete('Not implemented yet');
    }




} 