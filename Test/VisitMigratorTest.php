<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SiteMigration\Test;

use Piwik\Plugins\SiteMigration\Migrator\VisitMigrator;

/**
 * @group SiteMigration
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
    protected $toDbHelper;

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

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $siteMigrator;

    protected $gcHelper;

    public function setUp()
    {
        parent::setUp();

        $this->reset();
    }

    protected function reset()
    {
        $this->adapter = $this->getMock(
            'Zend_Db_Adapter_Pdo_Mysql',
            array('fetchRow', 'fetchAll', 'fetchCol', 'prepare', 'query'),
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


        $this->siteMigrator = $this->getMock(
            'Piwik\Plugins\SiteMigration\Migrator\SiteMigrator',
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


        $this->gcHelper = $this->getMock('Piwik\Plugins\SiteMigration\Helper\GCHelper', array(), array(), '', false);

        $this->visitMigrator = new VisitMigrator($this->toDbHelper, $this->gcHelper, $this->siteMigrator, $this->actionMigrator);
    }

    public function test_migrateVisits()
    {
        $visit = array(
            'idvisit'                   => 123,
            'idsite'                    => 2,
            'visit_exit_idaction_url'   => 4,
            'visit_exit_idaction_name'  => 6,
            'visit_entry_idaction_url'  => 8,
            'visit_entry_idaction_name' => 10,
        );

        $batchProvider = new \ArrayIterator(array($visit));

        $this->toDbHelper->expects($this->once())->method('executeInsert')->with('log_visit', $this->anything());
        $this->toDbHelper->expects($this->once())->method('lastInsertId')->will($this->returnValue(321));

        $this->siteMigrator->expects($this->once())->method('getNewId')->with(2)->will($this->returnValue(3));
        $this->actionMigrator->expects($this->exactly(4))->method('getNewId')->will($this->onConsecutiveCalls(
            5, 7, 9, 11
        ));

        $this->visitMigrator->migrate($batchProvider);

        $this->assertEquals(321, $this->visitMigrator->getNewId(123));
    }

    protected function setupDbHelperGetAdapter(\PHPUnit_Framework_MockObject_MockObject $adapter)
    {
        $adapter->expects($this->atLeastOnce())->method('getAdapter')->with()->will(
            $this->returnValue($this->adapter)
        );
    }
}
