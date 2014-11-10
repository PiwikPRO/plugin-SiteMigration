<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SiteMigration\Test;

use Piwik\Plugins\SiteMigration\Migrator\ConversionMigrator;

/**
 * @group SiteMigration
 */
class ConversionMigratorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ConversionMigrator
     */
    protected $conversionMigrator;

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
    protected $linkVisitActionMigrator;

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

        $this->linkVisitActionMigrator = $this->getMock(
            'Piwik\Plugins\SiteMigration\Migrator\LinkVisitActionMigrator',
            array(),
            array(),
            '',
            false
        );

        $this->gcHelper = $this->getMock('Piwik\Plugins\SiteMigration\Helper\GCHelper', array(), array(), '', false);

        $this->conversionMigrator = new ConversionMigrator($this->toDbHelper, $this->gcHelper, $this->siteMigrator, $this->visitMigrator, $this->actionMigrator, $this->linkVisitActionMigrator);

    }

    public function test_migrateConversions()
    {
        $conversion = array(
            'idsite'       => 1,
            'idvisit'      => 3,
            'idlink_va'    => 5,
            'idaction_url' => 7,
        );

        $this->toDbHelper->expects($this->once())->method('executeInsert')
            ->with(
                'log_conversion',
                $this->equalTo(
                    array(
                        'idsite'       => 2,
                        'idvisit'      => 4,
                        'idlink_va'    => 6,
                        'idaction_url' => 8,
                    )
                )
            );

        $this->siteMigrator->expects($this->once())->method('getNewId')->with(1)->willReturn(2);
        $this->visitMigrator->expects($this->once())->method('getNewId')->with(3)->willReturn(4);
        $this->linkVisitActionMigrator->expects($this->once())->method('getNewId')->with(5)->willReturn(6);
        $this->actionMigrator->expects($this->once())->method('getNewId')->with(7)->willReturn(8);


        $this->conversionMigrator->migrate(new \ArrayIterator(array($conversion)));
    }
}