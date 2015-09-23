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

class ConversionItemMigrator extends TableMigrator
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

    public function __construct(
        SiteDefinition $sourceDef,
        SiteDefinition $targetDef,
        GCHelper $gcHelper,
        TableMigrator $siteMigrator,
        TableMigrator $visitMigrator,
        ActionMigrator $actionMigrator
    ) {
        $this->siteMigrator   = $siteMigrator;
        $this->visitMigrator  = $visitMigrator;
        $this->actionMigrator = $actionMigrator;

        parent::__construct($sourceDef, $targetDef, $gcHelper);
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
