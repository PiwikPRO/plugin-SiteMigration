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

class ConversionMigrator extends TableMigrator
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
    protected $linkVisitActionMigrator;

    /**
     * @var LinkVisitActionMigrator
     */
    protected $actionMigrator;

    public function __construct(
        DBHelper $targetDb,
        GCHelper $gcHelper,
        TableMigrator $siteMigrator,
        TableMigrator $visitMigrator,
        ActionMigrator $actionMigrator,
        TableMigrator $linkVisitActionMigrator
    ) {
        $this->siteMigrator            = $siteMigrator;
        $this->visitMigrator           = $visitMigrator;
        $this->actionMigrator          = $actionMigrator;
        $this->linkVisitActionMigrator = $linkVisitActionMigrator;

        parent::__construct($targetDb, $gcHelper);
    }

    protected function translateRow(&$row)
    {
        $row['idsite']  = $this->siteMigrator->getNewId($row['idsite']);
        $row['idvisit'] = $this->visitMigrator->getNewId($row['idvisit']);

        if ($row['idlink_va']) {
            $row['idlink_va'] = $this->linkVisitActionMigrator->getNewId($row['idlink_va']);
        }

        if ($row['idaction_url']) {
            $row['idaction_url'] = $this->actionMigrator->getNewId(
                $row['idaction_url']
            );
        }
    }

    /**
     * @return string Name of the table migrated by this migration
     */
    protected function getTableName()
    {
        return 'log_conversion';
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
