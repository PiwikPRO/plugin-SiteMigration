<?php


namespace Piwik\Plugins\SiteMigrator\Test;

use Piwik\Plugins\SiteMigrator\Migrator\ArchiveMigrator;

/**
 * Class ArchiveMigratorTest
 * @package Piwik\Plugins\SiteMigrator\Test
 *
 * @group SiteMigrator
 */
class ArchiveMigratorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var ArchiveMigrator
     */
    protected $archiveMigrator;

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

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $statement;


    public function setUp()
    {
        parent::setUp();

        $this->reset();
    }

    protected function reset()
    {
        $this->siteMap         = $this->getMock('Piwik\Plugins\SiteMigrator\Model\IdMap', array('add', 'translate'));
        $this->idMapCollection = $idMapCollection = $this->getMock(
            'Piwik\Plugins\SiteMigrator\Model\IdMapCollection',
            array('getSiteMap', 'getActionMap')
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

        $this->statement = $this->getMock(
            'Zend_Db_Statement_Pdo',
            array('execute', 'fetch'),
            array(),
            '',
            false
        );

        $this->archiveMigrator = new ArchiveMigrator($this->fromDbHelper, $this->toDbHelper, $this->idMapCollection);

    }


    public function test_getArchiveList()
    {
        $prefixedArchives = array(
            'piwik_archive_num_01_2013',
            'piwik_archive_num_02_2013',
        );

        $archives = array(
            'archive_num_01_2013',
            'archive_num_02_2013',
        );

        $this->setupDbHelperGetAdapter($this->fromDbHelper);
        $this->fromDbHelper->expects($this->once())->method('prefixTable')->will($this->returnValue('piwik_'));
        $this->adapter->expects($this->once())->method('fetchCol')->will($this->returnValue($prefixedArchives));

        $list = $this->archiveMigrator->getArchiveList();

        foreach ($list as $k => $archive) {
            $this->assertEquals($archive, $archives[$k]);
        }
    }

    public function test_migrateArchive()
    {
        $idSite = 1;
        $this->setupDbHelperGetAdapter($this->toDbHelper);
        $this->setupDbHelperGetAdapter($this->fromDbHelper);

        $this->adapter->expects($this->exactly(2))->method('fetchCol')->will($this->onConsecutiveCalls(
            array(),
            array(321)
            ));

        $this->toDbHelper->expects($this->exactly(3))->method('prefixTable')->will($this->returnValue('piwik_'));
        $this->fromDbHelper->expects($this->exactly(2))->method('prefixTable')->will($this->returnValue('piwik_'));
        $this->adapter->expects($this->once())->method('prepare')->will($this->returnValue($this->statement));
        $this->statement->expects($this->once())->method('execute')->with(array($idSite));
        $this->statement->expects($this->exactly(2))->method('fetch')->will($this->onConsecutiveCalls(
                array('idarchive' => 123, 'idsite' => $idSite, 'data' => 'dummyData', 'name' => 'dummyArchive'),
                null
            ));


        $this->toDbHelper->expects($this->once())->method('acquireLock')->will($this->returnValue(true));
        $this->toDbHelper->expects($this->once())->method('releaseLock')->will($this->returnValue(true));
        $this->toDbHelper->expects($this->once())->method('executeInsert')->will($this->returnValue(true));
        $this->idMapCollection->expects($this->once())->method('getSiteMap')->with()->will($this->returnValue($this->siteMap));
        $this->siteMap->expects($this->once())->method('translate')->with($idSite)->will($this->returnValue(2));

        $this->archiveMigrator->migrateArchive('archive_num_01_2013', $idSite);
    }


    protected function setupDbHelperGetAdapter(\PHPUnit_Framework_MockObject_MockObject $adapter)
    {
        $adapter->expects($this->atLeastOnce())->method('getAdapter')->with()->will(
            $this->returnValue($this->adapter)
        );
    }

}