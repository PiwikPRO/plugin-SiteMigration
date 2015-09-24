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
 * @group SiteMigration
 */
class ActionMigratorTest extends BaseMigratorTest
{

    /**
     * @var actionMigrator
     */
    protected $actionMigrator;

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

        $this->reset();
    }

    protected function reset()
    {
        parent::reset();

        $this->statement = $this->getMock(
            'Zend_Db_Statement_Pdo',
            array('execute', 'fetch'),
            array(),
            '',
            false
        );

        $this->actionMigrator = new ActionMigrator($this->sourceDefinition, $this->targetDefinition, $this->gcHelper);
    }


    public function test_ensureActionIsMigrated_actionIsMigrated()
    {
        $this->actionMigrator->addNewId(123, 321);

        $this->actionMigrator->ensureActionIsMigrated(123);
    }

    public function test_ensureActionIsMigrated_actionIsNotMigrated()
    {
        $action = $this->dummyAction;

        $actionTranslated = $action;
        unset($actionTranslated['idaction']);

        $this->setupEnsureActionIsMigratedMigrationTest($action);

        $this->toDbHelper->expects($this->once())->method('executeInsert')->with(
            $this->equalTo('log_action'),
            $this->equalTo($actionTranslated)
        );

        $this->toDbHelper->expects($this->once())->method('lastInsertId')->will($this->returnValue(321));
        $this->actionMigrator->ensureActionIsMigrated(123);

        $this->assertEquals($this->actionMigrator->getNewId(123), 321);
    }

    public function test_ensureActionIsMigrated_actionDoesNotExist()
    {
        $action = null;

        $this->setupDbHelperGetAdapter($this->fromDbHelper);
        $this->assertFalse($this->actionMigrator->ensureActionIsMigrated(123));
    }

    public function test_ensureActionIsMigrated_actionAlreadyMigrated()
    {
        $existingActions = $this->dummyExistingActions;

        $this->actionMigrator->setExistingActions($existingActions);
        $this->toDbHelper->expects($this->never())->method('executeInsert');
        $this->adapter->expects($this->once())->method('fetchRow')->will($this->returnValue($this->dummyAction));
        $this->setupDbHelperGetAdapter($this->fromDbHelper);

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

        $this->actionMigrator->loadExistingActions();

        $this->assertEquals($this->dummyExistingActions, $this->actionMigrator->getExistingActions());
    }

    protected function setupEnsureActionIsMigratedMigrationTest($action)
    {
        $this->setupDbHelperGetAdapter($this->fromDbHelper);
        $this->adapter->expects($this->once())->method('fetchRow')->will($this->returnValue($action));
    }

    protected function setupDbHelperGetAdapter(\PHPUnit_Framework_MockObject_MockObject $helper)
    {
        $helper->expects($this->once())->method('getAdapter')->with()->will(
            $this->returnValue($this->adapter)
        );
    }

}