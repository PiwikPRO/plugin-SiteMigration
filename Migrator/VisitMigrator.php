<?php


namespace Piwik\Plugins\SiteMigration\Migrator;


use Piwik\Plugins\SiteMigration\Helper\DBHelper;
use Piwik\Plugins\SiteMigration\Helper\GCHelper;

class VisitMigrator extends Migrator
{
    /**
     * @var ActionMigrator
     */
    protected $actionMigrator;

    /**
     * @var Migrator
     */
    protected $siteMigrator;


    public function __construct(DBHelper $toDbHelper, GCHelper $gcHelper, Migrator $siteMigrator, ActionMigrator $actionMigrator)
    {
        $this->actionMigrator = $actionMigrator;
        $this->siteMigrator   = $siteMigrator;
        parent::__construct($toDbHelper, $gcHelper);
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