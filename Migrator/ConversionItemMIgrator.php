<?php


namespace Piwik\Plugins\SiteMigration\Migrator;


use Piwik\Plugins\SiteMigration\Helper\DBHelper;
use Piwik\Plugins\SiteMigration\Helper\GCHelper;

class ConversionItemMigrator extends Migrator
{

    /**
     * @var SiteMigrator
     */
    protected $siteMigrator;

    /**
     * @var VisitMigrator
     */
    protected $visitMigrator;

    /**
     * @var LinkVisitActionMigrator
     */
    protected $actionMigrator;

    protected $actionsToTranslate = array(
        'idaction_sku',
        'idaction_name',
        'idaction_category',
        'idaction_category2',
        'idaction_category3',
        'idaction_category4',
        'idaction_category5'
    );

    public function __construct(DBHelper $toDbHelper, GCHelper $gcHelper, Migrator $siteMigrator, Migrator $visitMigrator, ActionMigrator $actionMigrator)
    {
        $this->siteMigrator            = $siteMigrator;
        $this->visitMigrator           = $visitMigrator;
        $this->actionMigrator          = $actionMigrator;

        parent::__construct($toDbHelper, $gcHelper);
    }

    protected function translateRow(&$row)
    {
        $row['idsite']  = $this->siteMigrator->getNewId($row['idsite']);
        $row['idvisit'] = $this->visitMigrator->getNewId($row['idvisit']);

        foreach ($this->actionsToTranslate as $translationKey) {
            if ($row[$translationKey] == 0) {
                continue;
            }

            $row[$translationKey] = $this->actionMigrator->getNewId($row[$translationKey]);
        }
    }

    /**
     * @return string Name of the table migrated by this migration
     */
    protected function getTableName()
    {
        return 'log_conversion_item';
    }

    /**
     * @param array $row
     *
     * @return int  The current id stored in the given row
     */
    protected function getIdFromRow(&$row)
    {
        return null;
    }
}