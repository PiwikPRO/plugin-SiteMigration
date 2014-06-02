<?php


namespace Piwik\Plugins\SiteMigrator\Test;

use Piwik\Plugins\SiteMigrator\Helper\DBHelper;
use Piwik\Plugins\SiteMigrator\Migrator\VisitMigrator;

/**
 * Class VisitMigratorTest
 * @package Piwik\Plugins\SiteMigrator\Test
 *
 * @group SiteMigrator
 */
class VisitMigratorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var VisitMigrator
     */
    protected $visitMigrator;

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
    protected $visitMap;
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $visitActionMap;
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

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $actionMigrator;


    public function setUp()
    {
        parent::setUp();

        $this->reset();
    }

    protected function reset()
    {
        $this->siteMap         = $this->getMock('Piwik\Plugins\SiteMigrator\Model\IdMap', array('add', 'translate'));
        $this->visitMap        = $this->getMock('Piwik\Plugins\SiteMigrator\Model\IdMap', array('add', 'translate'));
        $this->visitActionMap  = $this->getMock('Piwik\Plugins\SiteMigrator\Model\IdMap', array('add', 'translate'));
        $this->actionMap       = $this->getMock('Piwik\Plugins\SiteMigrator\Model\IdMap', array('add', 'translate'));
        $this->idMapCollection = $idMapCollection = $this->getMock(
            'Piwik\Plugins\SiteMigrator\Model\IdMapCollection',
            array('getSiteMap', 'getVisitMap', 'getVisitActionMap', 'getActionMap')
        );

        $this->adapter      = $this->getMock(
            'Zend_Db_Adapter_Pdo_Mysql',
            array('fetchRow', 'fetchAll', 'fetchCol', 'prepare', 'query'),
            array(),
            '',
            false
        );
        $this->fromDbHelper = $this->getMock(
            'Piwik\Plugins\SiteMigrator\Helper\DBHelper',
            array('executeInsert', 'lastInsertId', 'getAdapter', 'prefixTable'),
            array(),
            '',
            false
        );

        $this->toDbHelper = $this->getMock(
            'Piwik\Plugins\SiteMigrator\Helper\DBHelper',
            array('executeInsert', 'lastInsertId', 'getAdapter', 'prefixTable', 'acquireLock', 'releaseLock'),
            array(),
            '',
            false
        );

        $this->actionMigrator = $this->getMock(
            'Piwik\Plugins\SiteMigrator\Migrator\ActionMigrator',
            array(),
            array(),
            '',
            false
        );

        $this->statement = $this->getMock(
            'Zend_Db_Statement_Pdo',
            array('execute', 'fetchColumn', 'closeCursor', 'fetch'),
            array(),
            '',
            false
        );

        $this->visitMigrator = new VisitMigrator($this->fromDbHelper, $this->toDbHelper, $this->idMapCollection, $this->actionMigrator);

    }


    public function test_andWhere()
    {
        $this->visitMigrator->andWhere('foo', 'bar', '>=');
        $this->assertEquals($this->visitMigrator->getWhere(), array(array('column' => 'foo', 'value' => 'bar', 'operator' => '>=')));
    }

    public function test_getVisitsQuery()
    {
        $this->setupDbHelperGetAdapter($this->fromDbHelper);
        $sql = "SELECT * FROM piwik_log_visit WHERE idsite = :idSite AND `foo` >= :param0 LIMIT 0, 10000";
        $this->fromDbHelper->expects($this->once())->method('prefixTable')->with('log_visit')->will($this->returnValue('piwik_log_visit'));
        $this->adapter->expects($this->once())->method('prepare')->with($sql)->will($this->returnValue($this->statement));
        $this->statement->expects($this->once())->method('execute')->with(array('param0' => 'bar', 'idSite' => 1));

        $this->visitMigrator->andWhere('foo', 'bar', '>=');

        $this->visitMigrator->getVisitsQuery(1);

    }

    public function test_migrateVisits()
    {
        $visit = array(
            'idvisit' => 123,
            'idsite'  => 2,
            'visit_exit_idaction_url' => 4,
            'visit_exit_idaction_name' => 6,
            'visit_entry_idaction_url' => 8,
            'visit_entry_idaction_name' => 10,

        );
        $this->setupDbHelperGetAdapter($this->fromDbHelper);
        $this->fromDbHelper->expects($this->atLeastOnce())->method('prefixTable');
        $this->adapter->expects($this->atLeastOnce())->method('prepare')->will($this->returnValue($this->statement));
        $this->statement->expects($this->atLeastOnce())->method('execute')->with();
        $this->statement->expects($this->exactly(2))->method('fetch')->will($this->onConsecutiveCalls(
                $visit,
                null
            ));
        $this->idMapCollection->expects($this->once())->method('getVisitMap')->will($this->returnValue($this->visitMap));
        $this->toDbHelper->expects($this->once())->method('executeInsert')->with('log_visit', $this->anything());
        $this->visitMap->expects($this->once())->method('add')->with($visit['idvisit'], $this->anything());
        $this->toDbHelper->expects($this->once())->method('lastInsertId')->will($this->returnValue(321));

        $this->idMapCollection->expects($this->once())->method('getSiteMap')->will($this->returnValue($this->siteMap));
        $this->idMapCollection->expects($this->exactly(4))->method('getActionMap')->will($this->returnValue($this->actionMap));
        $this->siteMap->expects($this->once())->method('translate')->with(2)->will($this->returnValue(3));
        $this->actionMigrator->expects($this->exactly(4))->method('ensureActionIsMigrated')->will($this->onConsecutiveCalls(
                5, 7, 9, 11
            ));

        $this->visitMigrator->migrateVisits(1);

    }

    public function test_getVisitCount()
    {
        $this->setupDbHelperGetAdapter($this->fromDbHelper);
        $sql = "SELECT COUNT(idvisit) FROM piwik_log_visit WHERE idsite = :idSite AND `foo` >= :param0";
        $this->fromDbHelper->expects($this->once())->method('prefixTable')->with('log_visit')->will($this->returnValue('piwik_log_visit'));
        $this->adapter->expects($this->once())->method('prepare')->with($sql)->will($this->returnValue($this->statement));
        $this->statement->expects($this->once())->method('execute')->with(array('param0' => 'bar', 'idSite' => 1));
        $this->statement->expects($this->once())->method('fetchColumn');

        $this->visitMigrator->andWhere('foo', 'bar', '>=');

        $this->visitMigrator->getVisitCount(1);
    }

    protected function setupDbHelperGetAdapter(\PHPUnit_Framework_MockObject_MockObject $adapter)
    {
        $adapter->expects($this->atLeastOnce())->method('getAdapter')->with()->will(
            $this->returnValue($this->adapter)
        );
    }

}