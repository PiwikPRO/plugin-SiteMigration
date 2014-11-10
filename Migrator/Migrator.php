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
 * Base class for all migrators.
 *
 * Contains common used methods.
 */
abstract class Migrator
{
    protected $skipped = 0;

    /**
     * @var DBHelper
     */
    protected $toDbHelper;

    protected $gcHelper;

    protected $idMap = array();

    public function __construct(DBHelper $toDbHelper, GCHelper $gcHelper)
    {
        $this->toDbHelper = $toDbHelper;
        $this->gcHelper   = $gcHelper;
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

        $this->toDbHelper->executeInsert($this->getTableName(), $row);

        if ($id != null) {
            $this->addNewId($id, $this->toDbHelper->lastInsertId());
        }
    }

    public function getNewId($oldId)
    {
        if (!array_key_exists($oldId, $this->idMap)) {
            throw new MissingIDTranslationException('Id ' . $oldId . ' not found in ' . __CLASS__);
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
     * @return array
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
