<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SiteMigration\Migrator;

/**
 * Base class for table migrators.
 */
abstract class TableMigrator extends BaseMigrator
{
    protected $skipped = 0;

    public function migrate(\Traversable $dataProvider)
    {
        foreach ($dataProvider as $row) {
            $this->processRow($row);
        }
    }

    protected function processRow(&$row)
    {
        $targetDbHelper = $this->targetDef->getDbHelper();

        $id = $this->getIdFromRow($row);
        $this->translateRow($row);

        $targetDbHelper->executeInsert($this->getTableName(), $row);

        if ($id != null) {
            $this->addNewId($id, $targetDbHelper->lastInsertId());
        }
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
