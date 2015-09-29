<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SiteMigration\Test\Unit;

use Piwik\Plugins\SiteMigration\DataProvider\BatchProvider;

/**
 * @group SiteMigration
 */
class BatchProviderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var BatchProvider
     */
    protected $batchProvider;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $fromDbHelper;

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
    protected $gcHelper;

    public function setUp()
    {
        parent::setUp();


        $this->fromDbHelper = $this->getMock(
            'Piwik\Plugins\SiteMigration\Helper\DBHelper',
            array('getAdapter'),
            array(),
            '',
            false
        );

        $this->adapter = $this->getMock(
            'Zend_Db_Adapter_Pdo_Mysql',
            array('fetchRow', 'fetchAll', 'prepare'),
            array(),
            '',
            false
        );

        $this->statement = $this->getMock(
            'Zend_Db_Statement_Pdo',
            array('execute', 'fetch', 'closeCursor', 'rowCount'),
            array(),
            '',
            false
        );

        $this->fromDbHelper->expects($this->any())->method('getAdapter')->willReturn($this->adapter);

        $this->gcHelper = $this->getMock('Piwik\Plugins\SiteMigration\Helper\GCHelper', array(), array(), '', false);
    }

    public function test_it_splitsQueryCorrectly()
    {
        $this->setupBatchProvider('SELECT * FROM table');

        $this->adapter->expects($this->exactly(2))->method('prepare')->withConsecutive(array('SELECT * FROM table LIMIT 0, 2'), array('SELECT * FROM table LIMIT 2, 2'))->willReturn($this->statement);
        $this->statement->expects($this->exactly(4))->method('fetch')->willReturn(array('foo' => 'bar'));

        $this->batchProvider->rewind();
        $this->batchProvider->next();
        $this->batchProvider->next();
        $this->batchProvider->next();
    }

    public function test_it_supportsMultipleQueries()
    {
        $this->setupBatchProvider(array('SELECT * FROM table1', 'SELECT * FROM table2', 'SELECT * FROM table3'));

        $this->adapter->expects($this->exactly(3))->method('prepare')->withConsecutive(
            array('SELECT * FROM table1 LIMIT 0, 2'),
            array('SELECT * FROM table2 LIMIT 0, 2'),
            array('SELECT * FROM table3 LIMIT 0, 2')
        )->willReturn($this->statement);

        $this->statement->expects($this->exactly(3))->method('fetch')->willReturn(null);

        $this->batchProvider->rewind();
        $this->batchProvider->next();
        $this->batchProvider->next();

    }

    protected function setupBatchProvider($queries)
    {
        $this->batchProvider = new BatchProvider($queries, $this->fromDbHelper, $this->gcHelper, 2);
    }
} 