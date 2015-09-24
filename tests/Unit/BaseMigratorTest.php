<?php
/**
 * Abraxas Medien
 * Developer: Adrian Tello
 * Date: 23.09.15
 * Time: 12:29
 */

namespace Piwik\Plugins\SiteMigration\tests\Unit;

use Piwik\Plugins\SiteMigration\Model\SiteDefinition;

abstract class BaseMigratorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $adapter;

    /**
     * @var SiteDefinition
     */
    protected $sourceDefinition;

    /**
     * @var SiteDefinition
     */
    protected $targetDefinition;

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
    protected $gcHelper;

    protected function reset()
    {
        $this->adapter = $this->getMock(
            'Zend_Db_Adapter_Pdo_Mysql',
            array('fetchRow', 'fetchAll', 'prepare'),
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

        $this->sourceDefinition = new SiteDefinition(1, $this->fromDbHelper); //The idsite isn't relevant here

        $this->toDbHelper = $this->getMock(
            'Piwik\Plugins\SiteMigration\Helper\DBHelper',
            array('executeInsert', 'lastInsertId', 'getAdapter', 'prefixTable'),
            array(),
            '',
            false
        );

        $this->targetDefinition = new SiteDefinition(null, $this->toDbHelper); //The idsite isn't relevant here

        $this->gcHelper = $this->getMock(
            'Piwik\Plugins\SiteMigration\Helper\GCHelper',
            array('enableGC', 'cleanup', 'cleanVariable'),
            array(),
            '',
            false
        );
    }
}