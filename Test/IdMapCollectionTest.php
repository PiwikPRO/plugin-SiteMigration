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


use Piwik\Plugins\SiteMigration\Model\IdMap;
use Piwik\Plugins\SiteMigration\Model\IdMapCollection;

/**
 * @group SiteMigration
 */
class IdMapCollectionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var IdMapCollection
     */
    private $idMapCollection;

    /**
     * @var IdMap $siteMap
     */
    protected $siteMap;

    /**
     * @var IdMap $siteMap
     */
    protected $actionMap;

    /**
     * @var IdMap $visitMap
     */
    protected $visitMap;

    /**
     * @var IdMap $visitActionMap
     */
    protected $visitActionMap;

    public function setUp()
    {
        parent::setUp();

        $this->reset();
    }

    protected function reset()
    {
        $this->idMapCollection = new IdMapCollection();
        $this->siteMap         = new IdMap();
        $this->actionMap       = new IdMap();
        $this->visitMap        = new IdMap();
        $this->visitActionMap  = new IdMap();
    }

    public function test_setSiteMap_and_getSiteMap()
    {
        $this->idMapCollection->setSiteMap($this->siteMap);
        $this->assertEquals($this->siteMap, $this->idMapCollection->getSiteMap());
    }

    public function test_setActionMap_and_getActionMap()
    {
        $this->idMapCollection->setActionMap($this->actionMap);
        $this->assertEquals($this->actionMap, $this->idMapCollection->getActionMap());
    }

    public function test_setVisitMap_and_getVisitMap()
    {
        $this->idMapCollection->setVisitMap($this->visitMap);
        $this->assertEquals($this->visitMap, $this->idMapCollection->getVisitMap());
    }

    public function test_setVisitActionMap_and_getVisitActionMap()
    {
        $this->idMapCollection->setVisitActionMap($this->actionMap);
        $this->assertEquals($this->visitMap, $this->idMapCollection->getVisitActionMap());
    }

}