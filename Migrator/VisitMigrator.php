<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SiteMigration\Migrator;

use Piwik\Plugins\SiteMigration\Helper\GCHelper;
use Piwik\Plugins\SiteMigration\Model\SiteDefinition;

class VisitMigrator extends TableMigrator
{
    /**
     * @var ActionMigrator
     */
    protected $actionMigrator;

    /**
     * @var TableMigrator
     */
    protected $siteMigrator;

    public function __construct(
        SiteDefinition $sourceDef,
        SiteDefinition $targetDef,
        GCHelper $gcHelper,
        TableMigrator $siteMigrator,
        ActionMigrator $actionMigrator
    )
    {
        $this->actionMigrator = $actionMigrator;
        $this->siteMigrator   = $siteMigrator;
        parent::__construct($sourceDef, $targetDef, $gcHelper);
    }

    protected function getTableName()
    {
        return 'log_visit';
    }

    protected function translateRow(&$row)
    {
        unset($row['idvisit']);

        $row['idsite']                    = $this->siteMigrator->getNewId($row['idsite']);
        $row['visit_exit_idaction_url']   = $this->actionMigrator->getNewId($row['visit_exit_idaction_url']);
        $row['visit_exit_idaction_name']  = $this->actionMigrator->getNewId($row['visit_exit_idaction_name']);
        $row['visit_entry_idaction_url']  = $this->actionMigrator->getNewId($row['visit_entry_idaction_url']);
        $row['visit_entry_idaction_name'] = $this->actionMigrator->getNewId($row['visit_entry_idaction_name']);
    }

    protected function getIdFromRow(&$row)
    {
        return $row['idvisit'];
    }

    public function getIdRanges($chunkSize = 1000)
    {
        return array_chunk(array_keys($this->getIdMap()), $chunkSize, true);
    }
}
