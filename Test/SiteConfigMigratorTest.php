<?php


namespace Piwik\Plugins\SiteMigrator\Test;


use Piwik\Plugins\SiteMigrator\Migrator\SiteConfigMigrator;

/**
 * Class SiteConfigMigratorTest
 * @package Piwik\Plugins\SiteMigrator\Test
 *
 * @group SiteMigrator
 */
class SiteConfigMigratorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var SiteConfigMigrator
     */
    protected $siteConfigMigrator;

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
    protected $adapter;

    public function setUp()
    {
        parent::setUp();

        $this->resetSiteConfig();
    }

    protected function resetSiteConfig()
    {
        $this->siteMap         = $this->getMock('Piwik\Plugins\SiteMigrator\Model\IdMap', array('add'));
        $this->idMapCollection = $idMapCollection = $this->getMock('Piwik\Plugins\SiteMigrator\Model\IdMapCollection', array('getSiteMap'));;
        $this->adapter         = $this->getMock('Zend_Db_Adapter_Pdo_Mysql', array('fetchRow'), array(), '', false);
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

        $this->siteConfigMigrator = new SiteConfigMigrator($this->fromDbHelper, $this->toDbHelper, $this->idMapCollection);

    }

    public function test_migrateSiteConfig()
    {

        $this->siteMap->expects($this->once())->method('add')->with($this->equalTo('1'), $this->equalTo(2));
        $this->adapter->expects($this->once())->method('fetchRow')->will(
            $this->returnValue(array('idsite' => 1, 'url' => 'http://example.com'))
        );

        $this->toDbHelper->expects($this->once())->method('lastInsertId')->will($this->returnValue(2));
        $this->toDbHelper->expects($this->once())->method('executeInsert')->will($this->returnValue(true));
        $this->fromDbHelper->expects($this->once())->method('getAdapter')->with()->will($this->returnValue($this->adapter));
        $this->idMapCollection->expects($this->once())->method('getSiteMap')->with()->will($this->returnValue($this->siteMap));

        $this->siteConfigMigrator->migrateSiteConfig(1);
    }

    public function test_migrateSiteGoals()
    {
        $goals = array(
            ['idsite' => 1, 'idgoal' => 1, 'name' => 'test goal 1'],
            ['idsite' => 1, 'idgoal' => 2, 'name' => 'test goal 2'],
        );
        $this->adapter->expects($this->once())->method('fetchAll')->will($this->returnValue($goals));
        $this->siteMap->expects($this->exactly(2))->method('translate')->will($this->returnValue(2));
        $this->fromDbHelper->expects($this->once())->method('getAdapter')->will($this->returnValue($this->adapter));
        $this->toDbHelper->expects($this->exactly(2))->method('executeInsert')->will($this->returnValue(true));

        $this->siteConfigMigrator->migrateSiteGoals(1);
    }


} 