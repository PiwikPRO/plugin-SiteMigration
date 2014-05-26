<?php


namespace Piwik\Plugins\SiteMigrator\Test;


use Piwik\Plugins\SiteMigrator\Model\IdMap;

/**
 * @group SiteMigrator
 */
class IdMapTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var IdMap
     */
    private $idMap;

    public function setUp()
    {
        parent::setUp();

        $this->resetIdMap();
    }

    protected function resetIdMap($translations = array())
    {
        $this->idMap = new IdMap();

        foreach($translations as $key => $val)
        {
            $this->idMap->add($key, $val);
        }
    }

    public function test_add_shouldAddTranslation()
    {
        $this->resetIdMap();
        $this->assertEquals($this->idMap->getIds(), array());
        $this->idMap->add(1,2);
        $this->assertEquals($this->idMap->getIds(), array(1 => 2));
    }

    public function test_translate_shouldReturnTranslatedValue()
    {
        $this->resetIdMap(array(1 => 4, 2 => 3, 5 => 10));

        $this->assertEquals(4, $this->idMap->translate(1));
        $this->assertEquals(3, $this->idMap->translate(2));
        $this->assertEquals(10, $this->idMap->translate(5));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Value for key 12 not found
     */
    public function test_translate_shouldThrowInvalidArgumentExceptionOnNoMatch()
    {
        $this->resetIdMap();
        $this->idMap->translate(12);
    }

    public function test_get_shouldReturnAllTranslations()
    {
        $translations = array(1 => 4, 2 => 3);
        $this->resetIdMap($translations);

        $this->assertEquals($translations, $this->idMap->getIds());
    }
}