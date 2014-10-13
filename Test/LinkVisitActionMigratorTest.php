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
    protected $toDbHelper;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $actionMigrator;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $siteMigrator;


    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $visitMigrator;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $gcHelper;


    public function setUp()
    {
        parent::setUp();

        $this->reset();
    }

    protected function reset()
    {

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

        $this->visitMigrator = $this->getMock(
            'Piwik\Plugins\SiteMigration\Migrator\SiteMigrator',
            array(),
            array(),
            '',
            false
        );

        $this->gcHelper = $this->getMock('Piwik\Plugins\SiteMigration\Helper\GCHelper', array(), array(), '', false);

        $this->linkVisitActionMigrator = new LinkVisitActionMigrator($this->toDbHelper, $this->gcHelper, $this->siteMigrator, $this->visitMigrator, $this->actionMigrator);

    }


    public function test_migrateVisitActions()
    {
        $linkVisitAction = array(
            'idsite'                  => 1,
            'idvisit'                 => 3,
            'idlink_va'               => 5,
            'idaction_url'            => 7,
            'idaction_url_ref'        => 9,
            'idaction_name'           => 11,
            'idaction_name_ref'       => 13,
            'idaction_event_category' => 15,
            'idaction_event_action'   => 17,
        );

        $this->toDbHelper->expects($this->once())->method('executeInsert')->with(
            'log_link_visit_action',
            $this->equalTo(
                array(
                    'idsite'                  => 2,
                    'idvisit'                 => 4,
                    'idaction_url'            => 8,
                    'idaction_url_ref'        => 10,
                    'idaction_name'           => 12,
                    'idaction_name_ref'       => 14,
                    'idaction_event_category' => 16,
                    'idaction_event_action'   => 18,
                )
            )
        );
        $this->toDbHelper->expects($this->once())->method('lastInsertId')->will($this->returnValue(6));

        $this->siteMigrator->expects($this->once())->method('getNewId')->with(1)->willReturn(2);
        $this->visitMigrator->expects($this->once())->method('getNewId')->with(3)->willReturn(4);

        $this->actionMigrator->expects($this->exactly(6))->method('getNewId')->will($this->onConsecutiveCalls(
            8, 10, 12, 14, 16, 18
        ));

        $this->linkVisitActionMigrator->migrate(new \ArrayIterator(array($linkVisitAction)));

        $this->assertEquals(6, $this->linkVisitActionMigrator->getNewId(5));
    }
}