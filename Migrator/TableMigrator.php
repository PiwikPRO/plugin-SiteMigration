<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SiteMigration\Migrator;

use Piwik\Plugins\SiteMigration\Exception\MissingIDTranslationException;
use Piwik\Plugins\SiteMigration\Helper\DBHelper;
use Piwik\Plugins\SiteMigration\Helper\GCHelper;

/**
 * Base class for table migrators.
 */
abstract class TableMigrator
{
    protected $skipped = 0;

    /**
     * @var DBHelper
     */
    protected $targetDb;

    /**
     * @var GCHelper
     */
    protected $gcHelper;

    /**
     * @var int[]
     */
    protected $idMap = array();

    public function __construct(DBHelper $targetDb, GCHelper $gcHelper)
    {
        $this->targetDb = $targetDb;
        $this->gcHelper = $gcHelper;
    }

    public function migrate(\Traversable $dataProvider)
    {
        foreach ($dataProvider as $row) {
            $this->processRow($row);
        }
    }

    protected function processRow(&$row)
    {
        $id = $this->getIdFromRow($row);
        $this->translateRow($row);

        $this->targetDb->executeInsert($this->getTableName(), $row);

        if ($id != null) {
            $this->addNewId($id, $this->targetDb->lastInsertId());
        }
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

    public function checkColumns()
    {
    }

    protected abstract function translateRow(&$row);

    /**
     * @return string Name of the table migrated by this migration
     */
    protected abstract function getTableName();

    /**
     * @param array $row
     *
     * @return int  The current id stored in the given row
     */
    protected abstract function getIdFromRow(&$row);
}
