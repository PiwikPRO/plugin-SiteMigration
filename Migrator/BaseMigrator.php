<?php
/**
 * Abraxas Medien
 * Developer: Adrian Tello
 * Date: 23.09.15
 * Time: 09:55
 */

namespace Piwik\Plugins\SiteMigration\Migrator;

use Piwik\Plugins\SiteMigration\Helper\GCHelper;
use Piwik\Plugins\SiteMigration\Model\SiteDefinition;

abstract class BaseMigrator
{

    protected $sourceDef;

    protected $targetDef;

    /**
     * @var GCHelper
     */
    protected $gcHelper;

    /**
     * @var int[]
     */
    protected $idMap = array();

    public function __construct(
        SiteDefinition $sourceDef,
        SiteDefinition $targetDef,
        GCHelper $gcHelper
    )
    {
        $this->sourceDef = $sourceDef;
        $this->targetDef = $targetDef;

        $this->gcHelper = $gcHelper;
    }

    /**
     * @param int $oldId
     * @return int
     */
    public function getNewId($oldId)
    {
        if (!array_key_exists($oldId, $this->idMap)) {
            throw new \InvalidArgumentException('Id ' . $oldId . ' not found in ' . __CLASS__);
        }

        return $this->idMap[$oldId];
    }

    /**
     * @param int $oldId
     * @param int $newId
     */
    public function addNewId($oldId, $newId)
    {
        $this->idMap[$oldId] = $newId;
    }

    /**
     * @return int[]
     */
    public function getIdMap()
    {
        return $this->idMap;
    }

}