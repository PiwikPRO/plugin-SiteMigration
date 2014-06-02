<?php


namespace Piwik\Plugins\SiteMigrator\Test;

use Piwik\Plugins\SiteMigrator\Migrator\ConversionMigrator;

/**
 * Class ConversionMigratorTest
 * @package Piwik\Plugins\SiteMigrator\Test
 *
 * @group SiteMigrator
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
        $this->visitMap        = $this->getMock(
            'Piwik\Plugins\SiteMigrator\Model\IdMap',
            array('add', 'translate', 'getIds')
        );
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
            array('execute', 'fetch', 'closeCursor'),
            array(),
            '',
            false
        );

        $this->conversionMigrator = new ConversionMigrator($this->fromDbHelper, $this->toDbHelper, $this->idMapCollection, $this->actionMigrator);

    }


    public function test_migrateConversions()
    {
        $conversion = array(
            'idsite'       => 1,
            'idvisit'      => 3,
            'idlink_va'    => 5,
            'idaction_url' => 7,
        );
        $this->setupDbHelperGetAdapter($this->fromDbHelper);
        $this->adapter->expects($this->once())->method('prepare')->will($this->returnValue($this->statement));
        $this->statement->expects($this->once())->method('execute');
        $this->statement->expects($this->exactly(2))->method('fetch')->will(
            $this->onConsecutiveCalls(
                $conversion,
                null
            )
        );
        $this->statement->expects($this->once())->method('closeCursor');

        $this->toDbHelper->expects($this->once())->method('executeInsert')->with('log_conversion', $this->anything());

        $this->idMapCollection->expects($this->once())->method('getSiteMap')->will($this->returnValue($this->siteMap));
        $this->idMapCollection->expects($this->exactly(2))->method('getVisitMap')->will(
            $this->returnValue($this->visitMap)
        );
        $this->idMapCollection->expects($this->once())->method('getVisitActionMap')->will(
            $this->returnValue($this->visitActionMap)
        );
        $this->idMapCollection->expects($this->once())->method('getActionMap')->will(
            $this->returnValue($this->actionMap)
        );

        $this->siteMap->expects($this->once())->method('translate')->with(1)->will($this->returnValue(2));
        $this->visitMap->expects($this->once())->method('translate')->with(3)->will($this->returnValue(4));
        $this->visitMap->expects($this->once())->method('getIds')->will($this->returnValue(array(1, 2)));
        $this->visitActionMap->expects($this->once())->method('translate')->with(5)->will($this->returnValue(6));

        $this->actionMigrator->expects($this->once())->method('ensureActionIsMigrated');
        $this->actionMap->expects($this->once())->method('translate')->with(7)->will($this->returnValue(8));

        $this->conversionMigrator->migrateConversions(1);
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
        $this->setupDbHelperGetAdapter($this->fromDbHelper);
        $this->adapter->expects($this->once())->method('prepare')->will($this->returnValue($this->statement));
        $this->statement->expects($this->once())->method('execute');
        $this->statement->expects($this->exactly(2))->method('fetch')->will(
            $this->onConsecutiveCalls(
                $conversionItem,
                null
            )
        );
        $this->statement->expects($this->once())->method('closeCursor');

        $this->toDbHelper->expects($this->once())->method('executeInsert')->with(
            'log_conversion_item',
            $this->anything()
        );

        $this->idMapCollection->expects($this->once())->method('getSiteMap')->will($this->returnValue($this->siteMap));
        $this->idMapCollection->expects($this->exactly(2))->method('getVisitMap')->will(
            $this->returnValue($this->visitMap)
        );
        $this->idMapCollection->expects($this->exactly(6))->method('getActionMap')->will(
            $this->returnValue($this->actionMap)
        );

        $this->siteMap->expects($this->once())->method('translate')->with(1)->will($this->returnValue(2));
        $this->visitMap->expects($this->once())->method('translate')->with(3)->will($this->returnValue(4));
        $this->visitMap->expects($this->once())->method('getIds')->will($this->returnValue(array(1, 2)));

        $this->actionMigrator->expects($this->exactly(6))->method('ensureActionIsMigrated');
        $this->actionMap->expects($this->exactly(6))->method('translate')->will(
            $this->onConsecutiveCalls(2, 4, 6, 8, 12, 14, 16, 18, 20)
        );

        $this->conversionMigrator->migrateConversionItems(1);
    }


    protected function setupDbHelperGetAdapter(\PHPUnit_Framework_MockObject_MockObject $adapter)
    {
        $adapter->expects($this->atLeastOnce())->method('getAdapter')->with()->will(
            $this->returnValue($this->adapter)
        );
    }

}