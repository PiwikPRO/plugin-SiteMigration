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

use Piwik\Plugins\SiteMigration\Migrator\ConversionItemMigrator;

/**
 * Class ConversionItemMigratorTest
 * @package Piwik\Plugins\SiteMigration\Test
 *
 * @group SiteMigration
 */
class ConversionItemMigratorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var ConversionItemMigrator
     */
    protected $conversionItemMigrator;

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

        $this->conversionItemMigrator = new ConversionItemMigrator($this->toDbHelper, $this->gcHelper, $this->siteMigrator, $this->visitMigrator, $this->actionMigrator);

    }

    public function test_migrateConversionItems()
    {
        $conversionItem = array(
            'idsite'             => 1,
            'idvisit'            => 3,
            'idlink_va'          => 5,
            'idaction_sku'       => 7,
            'idaction_name'      => 0,
            'idaction_category'  => 11,
            'idaction_category2' => 13,
            'idaction_category3' => 15,
            'idaction_category4' => 17,
            'idaction_category5' => 19
        );

        $this->toDbHelper->expects($this->once())->method('executeInsert')->with(
            'log_conversion_item',
            $this->anything()
        );

        $this->siteMigrator->expects($this->once())->method('getNewId')->with(1)->willReturn(2);
        $this->visitMigrator->expects($this->once())->method('getNewId')->with(3)->willReturn(4);
        $this->actionMigrator->expects($this->exactly(6))->method('getNewId')->will(
            $this->onConsecutiveCalls(2, 4, 6, 8, 12, 14, 16, 18, 20)
        );

        $this->conversionItemMigrator->migrate(new \ArrayIterator(array($conversionItem)));
    }

}