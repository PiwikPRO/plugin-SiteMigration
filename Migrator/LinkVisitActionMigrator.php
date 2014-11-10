<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SiteMigration\Migrator;

use Piwik\Plugins\SiteMigration\Helper\DBHelper;
use Piwik\Plugins\SiteMigration\Helper\GCHelper;

class LinkVisitActionMigrator extends TableMigrator
{
    /**
     * @var TableMigrator
     */
    protected $siteMigrator;

    /**
     * @var TableMigrator
     */
    protected $visitMigrator;

    /**
     * @var TableMigrator
     */
    protected $actionMigrator;

    public function __construct(
        DBHelper $targetDb,
        GCHelper $gcHelper,
        TableMigrator $siteMigrator,
        TableMigrator $visitMigrator,
        ActionMigrator $actionMigrator
    ) {
        $this->siteMigrator   = $siteMigrator;
        $this->visitMigrator  = $visitMigrator;
        $this->actionMigrator = $actionMigrator;

        parent::__construct($targetDb, $gcHelper);
    }


    protected function translateRow(&$row)
    {
        unset($row['idlink_va']);

        $row['idsite']  = $this->siteMigrator->getNewId($row['idsite']);
        $row['idvisit'] = $this->visitMigrator->getNewId($row['idvisit']);

        $row['idaction_url']            = $this->actionMigrator->getNewId($row['idaction_url']);
        $row['idaction_url_ref']        = $this->actionMigrator->getNewId($row['idaction_url_ref']);
        $row['idaction_name']           = $this->actionMigrator->getNewId($row['idaction_name']);
        $row['idaction_name_ref']       = $this->actionMigrator->getNewId($row['idaction_name_ref']);
        $row['idaction_event_category'] = $this->actionMigrator->getNewId($row['idaction_event_category']);
        $row['idaction_event_action']   = $this->actionMigrator->getNewId($row['idaction_event_action']);
    }

    /**
     * @return string Name of the table migrated by this migration
     */
    protected function getTableName()
    {
        return 'log_link_visit_action';
    }

    /**
     * @param array $row
     *
     * @return int  The current id stored in the given row
     */
    protected function getIdFromRow(&$row)
    {
        return $row['idlink_va'];
    }
}
