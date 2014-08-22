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

use Piwik\Plugins\SiteMigration\Migrator\LinkVisitActionMigrator;

/**
 * Class LinkVisitActionMigratorTest
 * @package Piwik\Plugins\SiteMigration\Test
 *
 * @group SiteMigration
 */
class LinkVisitActionMigratorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var LinkVisitActionMigrator
     */
    protected $linkVisitActionMigrator;

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
        $this->siteMap         = $this->getMock('Piwik\Plugins\SiteMigration\Model\IdMap', array('add', 'translate'));
        $this->visitMap        = $this->getMock(
            'Piwik\Plugins\SiteMigration\Model\IdMap',
            array('add', 'translate', 'getIds')
        );
        $this->visitActionMap  = $this->getMock('Piwik\Plugins\SiteMigration\Model\IdMap', array('add', 'translate'));
        $this->actionMap       = $this->getMock('Piwik\Plugins\SiteMigration\Model\IdMap', array('add', 'translate'));
        $this->idMapCollection = $idMapCollection = $this->getMock(
            'Piwik\Plugins\SiteMigration\Model\IdMapCollection',
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
            'Piwik\Plugins\SiteMigration\Helper\DBHelper',
            array('executeInsert', 'lastInsertId', 'getAdapter', 'prefixTable'),
            array(),
            '',
            false
        );

        $this->toDbHelper = $this->getMock(
            'Piwik\Plugins\SiteMigration\Helper\DBHelper',
            array('executeInsert', 'lastInsertId', 'getAdapter', 'prefixTable', 'acquireLock', 'releaseLock'),
            array(),
            '',
            false
        );

        $this->actionMigrator = $this->getMock(
            'Piwik\Plugins\SiteMigration\Migrator\ActionMigrator',
            array(),
            array(),
            '',
            false
        );

        $this->statement = $this->getMock(
            'Zend_Db_Statement_Pdo',
            array('execute', 'fetch', 'closeCursor'),
            array(),
            '',
            false
        );

        $this->linkVisitActionMigrator = new LinkVisitActionMigrator($this->fromDbHelper, $this->toDbHelper, $this->idMapCollection, $this->actionMigrator);

    }


    public function test_migrateVisitActions()
    {
        $linkVisitAction = array(
            'idsite'       => 1,
            'idvisit'      => 3,
            'idlink_va'    => 5,
            'idaction_url' => 7,
            'idaction_url_ref' => 9,
            'idaction_name' => 11,
            'idaction_name_ref' => 13,
            'idaction_event_category' => 15,
            'idaction_event_action' => 17,
        );

        $this->setupDbHelperGetAdapter($this->fromDbHelper);
        $this->adapter->expects($this->once())->method('prepare')->will($this->returnValue($this->statement));
        $this->statement->expects($this->once())->method('execute');
        $this->statement->expects($this->exactly(2))->method('fetch')->will(
            $this->onConsecutiveCalls(
                $linkVisitAction,
                null
            )
        );
        $this->statement->expects($this->once())->method('closeCursor');

        $this->toDbHelper->expects($this->once())->method('executeInsert')->with(
            'log_link_visit_action',
            $this->anything()
        );
        $this->toDbHelper->expects($this->once())->method('lastInsertId')->will($this->returnValue(6));

        $this->idMapCollection->expects($this->once())->method('getSiteMap')->will($this->returnValue($this->siteMap));
        $this->idMapCollection->expects($this->exactly(2))->method('getVisitMap')->will(
            $this->returnValue($this->visitMap)
        );
        $this->idMapCollection->expects($this->once())->method('getVisitActionMap')->will(
            $this->returnValue($this->visitActionMap)
        );
        $this->idMapCollection->expects($this->exactly(6))->method('getActionMap')->will(
            $this->returnValue($this->actionMap)
        );

        $this->visitActionMap->expects($this->once())->method('add')->with(
            $linkVisitAction['idlink_va'],
            $this->anything()
        );


        $this->siteMap->expects($this->once())->method('translate')->with(1)->will($this->returnValue(2));
        $this->visitMap->expects($this->once())->method('translate')->with(3)->will($this->returnValue(4));
        $this->visitMap->expects($this->once())->method('getIds')->will($this->returnValue(array(1, 2)));

        $this->actionMigrator->expects($this->exactly(6))->method('ensureActionIsMigrated');
        $this->actionMap->expects($this->exactly(6))->method('translate')->will($this->onConsecutiveCalls(
                8, 10, 12, 14, 16, 18
            ));

        $this->linkVisitActionMigrator->migrateVisitActions(1);
    }


    protected function setupDbHelperGetAdapter(\PHPUnit_Framework_MockObject_MockObject $adapter)
    {
        $adapter->expects($this->atLeastOnce())->method('getAdapter')->with()->will(
            $this->returnValue($this->adapter)
        );
    }

}