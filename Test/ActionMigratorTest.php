<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */


namespace Piwik\Plugins\SiteMigration\Test;


use Piwik\Plugins\SiteMigration\Migrator\ActionMigrator;

/**
 * Class ActionMigratorTest
 * @package Piwik\Plugins\SiteMigration\Test
 *
 * @group SiteMigration
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

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $statement;


    protected $dummyAction = array(
        'idaction'   => 123,
        'name'       => 'Dummy action',
        'hash'       => '1239394545',
        'type'       => '4',
        'url_prefix' => null,
    );

    protected $dummyExistingActions = array('4' => array('1239394545' => 321));

    public function setUp()
    {
        parent::setUp();

        $this->resetActionConfig();
    }

    protected function resetActionConfig()
    {
        $this->siteMap         = $this->getMock('Piwik\Plugins\SiteMigration\Model\IdMap', array('add', 'translate'));
        $this->actionMap       = $this->getMock('Piwik\Plugins\SiteMigration\Model\IdMap', array('add', 'translate'));

        $this->idMapCollection = $idMapCollection = $this->getMock(
            'Piwik\Plugins\SiteMigration\Model\IdMapCollection',
            array('getSiteMap', 'getActionMap')
        );

        $this->adapter      = $this->getMock(
            'Zend_Db_Adapter_Pdo_Mysql',
            array('fetchRow', 'fetchAll', 'prepare'),
            array(),
            '',
            false
        );
        $this->fromDbHelper = $this->getMock(
            'Piwik\Plugins\SiteMigration\Helper\DBHelper',
            array('executeInsert', 'lastInsertId', 'getAdapter', 'prefixTable'),
            array(),
            '',
            false
        );

        $this->toDbHelper = $this->getMock(
            'Piwik\Plugins\SiteMigration\Helper\DBHelper',
            array('executeInsert', 'lastInsertId', 'getAdapter', 'prefixTable'),
            array(),
            '',
            false
        );

        $this->statement = $this->getMock(
            'Zend_Db_Statement_Pdo',
            array('execute', 'fetch'),
            array(),
            '',
            false
        );

        $this->actionMigrator = new ActionMigrator($this->fromDbHelper, $this->toDbHelper, $this->idMapCollection);

    }


    public function test_ensureActionIsMigrated_actionIsMigrated()
    {

        $this->idMapCollection->expects($this->once())->method('getActionMap')->with()->will(
            $this->returnValue($this->actionMap)
        );
        $this->actionMap->expects($this->once())->method('translate')->with($this->equalTo(123))->will(
            $this->returnValue(321)
        );

        $this->actionMigrator->ensureActionIsMigrated(123);
    }

    public function test_ensureActionIsMigrated_actionIsNotMigrated()
    {
        $action = $this->dummyAction;

        $actionTranslated = $action;
        unset($actionTranslated['idaction']);

        $this->setupEnureActionIsMigratedMigrationTest($action);

        $this->toDbHelper->expects($this->once())->method('executeInsert')->with(
            $this->equalTo('log_action'),
            $this->equalTo($actionTranslated)
        );

        $this->toDbHelper->expects($this->once())->method('lastInsertId')->will($this->returnValue(321));
        $this->actionMap->expects($this->once())->method('add')->with($this->equalTo(123), $this->equalTo(321));

        $this->assertTrue($this->actionMigrator->ensureActionIsMigrated(123));
    }

    public function test_ensureActionIsMigrated_actionDoesNotExist()
    {
        $action = null;

        $this->setupEnureActionIsMigratedMigrationTest($action);
        $this->assertFalse($this->actionMigrator->ensureActionIsMigrated(123));
    }

    public function test_ensureActionIsMigrated_actionAlreadyMigrated()
    {
        $action          = $this->dummyAction;
        $existingActions = $this->dummyExistingActions;

        $this->setupEnureActionIsMigratedMigrationTest($action);
        $this->actionMigrator->setExistingActions($existingActions);
        $this->toDbHelper->expects($this->never())->method('executeInsert');

        $this->actionMap->expects($this->once())->method('add')->with($this->equalTo(123), $this->equalTo(321));

        $this->assertTrue($this->actionMigrator->ensureActionIsMigrated(123));
    }

    public function test_addExistingAction_addAction()
    {
        $action             = $this->dummyAction;
        $action['idaction'] = 321;
        $this->actionMigrator->addExistingAction($action);
        $this->assertEquals($this->dummyExistingActions, $this->actionMigrator->getExistingActions());
    }

    public function test_loadExistingActions()
    {
        $action             = $this->dummyAction;
        $action['idaction'] = 321;
        $this->setupDbHelperGetAdapter($this->toDbHelper);

        $this->adapter->expects($this->once())->method('prepare')->will($this->returnValue($this->statement));
        $this->toDbHelper->expects($this->once())->method('prefixTable')->will($this->returnValue('piwik_'));
        $this->statement->expects($this->once())->method('execute');
        $this->statement->expects($this->exactly(2))->method('fetch')->will($this->onConsecutiveCalls($action, null));

        $this->assertTrue($this->actionMigrator->loadExistingActions());
        $this->assertEquals($this->dummyExistingActions, $this->actionMigrator->getExistingActions());
    }

    protected function setupEnureActionIsMigratedMigrationTest($action)
    {
        $this->idMapCollection->expects($this->atLeastOnce())->method('getActionMap')->with()->will(
            $this->returnValue($this->actionMap)
        );
        $this->actionMap->expects($this->once())->method('translate')->with($this->equalTo(123))->will(
            $this->throwException(new \InvalidArgumentException('Nope'))
        );
        $this->setupDbHelperGetAdapter($this->fromDbHelper);
        $this->adapter->expects($this->once())->method('fetchRow')->will($this->returnValue($action));
    }

    protected function setupDbHelperGetAdapter(\PHPUnit_Framework_MockObject_MockObject $adapter)
    {
        $adapter->expects($this->once())->method('getAdapter')->with()->will(
            $this->returnValue($this->adapter)
        );
    }

}