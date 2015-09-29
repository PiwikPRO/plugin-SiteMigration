<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SiteMigration\Test\Unit\Archive;

use Piwik\Plugins\SiteMigration\Migrator\Archive\ArchiveLister;

class ArchiveListerTest extends \PHPUnit_Framework_TestCase
{
    public function testNoDatesAndNoPrefix()
    {
        $dbAdapter = $this->createDbAdapter(array(
            'archive_numeric_2014_10',
            'archive_numeric_2014_11',
        ));
        $db = $this->createDbHelper($dbAdapter, '');

        $lister = new ArchiveLister($db);

        $expected = array(
            '2014_10',
            '2014_11',
        );
        $this->assertEquals($expected, $lister->getArchiveList());
    }

    public function testWithPrefix()
    {
        $dbAdapter = $this->createDbAdapter(array(
            'piwik_archive_numeric_2014_10',
            'piwik_archive_numeric_2014_11',
        ));
        $db = $this->createDbHelper($dbAdapter, 'piwik_');

        $lister = new ArchiveLister($db);

        $expected = array(
            '2014_10',
            '2014_11',
        );
        $this->assertEquals($expected, $lister->getArchiveList());
    }

    public function testWithDates()
    {
        $dbAdapter = $this->createDbAdapter(array(
            'archive_numeric_2013_11',
            'archive_numeric_2014_09',
            'archive_numeric_2014_10',
            'archive_numeric_2014_11',
            'archive_numeric_2014_12',
            'archive_numeric_2015_11',
        ));
        $db = $this->createDbHelper($dbAdapter, '');

        $lister = new ArchiveLister($db);

        $expected = array(
            '2014_10',
            '2014_11',
        );
        $this->assertEquals($expected, $lister->getArchiveList(new \DateTime('2014-10-01'), new \DateTime('2014-11-01')));
    }

    public function testWithFromDate()
    {
        $dbAdapter = $this->createDbAdapter(array(
            'archive_numeric_2013_11',
            'archive_numeric_2014_09',
            'archive_numeric_2014_10',
            'archive_numeric_2014_11',
            'archive_numeric_2014_12',
            'archive_numeric_2015_11',
        ));
        $db = $this->createDbHelper($dbAdapter, '');

        $lister = new ArchiveLister($db);

        $expected = array(
            '2014_10',
            '2014_11',
            '2014_12',
            '2015_11',
        );
        $this->assertEquals($expected, $lister->getArchiveList(new \DateTime('2014-10-01')));
    }

    public function testWithToDate()
    {
        $dbAdapter = $this->createDbAdapter(array(
            'archive_numeric_2013_11',
            'archive_numeric_2014_09',
            'archive_numeric_2014_10',
            'archive_numeric_2014_11',
            'archive_numeric_2014_12',
            'archive_numeric_2015_11',
        ));
        $db = $this->createDbHelper($dbAdapter, '');

        $lister = new ArchiveLister($db);

        $expected = array(
            '2013_11',
            '2014_09',
            '2014_10',
            '2014_11',
        );
        $this->assertEquals($expected, $lister->getArchiveList(null, new \DateTime('2014-11-01')));
    }

    private function createDbAdapter(array $archiveTables)
    {
        $dbAdapter = $this->getMockForAbstractClass('Zend_Db_Adapter_Abstract', array(), '', false, false, true, array('fetchCol'));

        $dbAdapter->expects($this->any())
            ->method('fetchCol')
            ->willReturn($archiveTables);

        return $dbAdapter;
    }

    private function createDbHelper($dbAdapter, $tablePrefix)
    {
        $db = $this->getMock('Piwik\Plugins\SiteMigration\Helper\DBHelper', array(), array(), '', false);
        $db->expects($this->any())
            ->method('getAdapter')
            ->willReturn($dbAdapter);

        $db->expects($this->any())
            ->method('prefixTable')
            ->willReturnCallback(function ($table) use ($tablePrefix) {
                return $tablePrefix . $table;
            });

        return $db;
    }
}
