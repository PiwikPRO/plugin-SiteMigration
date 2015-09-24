<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SiteMigration\Migrator;

use Piwik\Log;
use Piwik\Plugins\SiteMigration\DataProvider\BatchProvider;
use Piwik\Plugins\SiteMigration\Helper\GCHelper;
use Piwik\Plugins\SiteMigration\Migrator\Archive\ArchiveLister;
use Piwik\Plugins\SiteMigration\Model\SiteDefinition;

class Migrator
{
    /**
     * @var SiteDefinition
     */
    private $sourceDefinition;

    /**
     * @var SiteDefinition
     */
    private $targetDefinition;

    /**
     * @var GCHelper
     */
    private $gcHelper;
    /**
     * @var SiteMigrator
     */
    private $siteMigrator;
    /**
     * @var SiteGoalMigrator
     */
    private $siteGoalMigrator;
    /**
     * @var SiteUrlMigrator
     */
    private $siteUrlMigrator;
    /**
     * @var ActionMigrator
     */
    private $actionMigrator;
    /**
     * @var VisitMigrator
     */
    private $visitMigrator;
    /**
     * @var LinkVisitActionMigrator
     */
    private $visitActionMigrator;
    /**
     * @var ConversionMigrator
     */
    private $conversionMigrator;
    /**
     * @var ConversionItemMigrator
     */
    private $conversionItemMigrator;
    /**
     * @var ArchiveMigrator
     */
    private $archiveMigrator;

    /**
     * @var MigratorSettings
     */
    private $settings;

    /**
     * @var ArchiveLister
     */
    private $archiveLister;

    public function __construct(
        SiteDefinition $sourceDefinition,
        SiteDefinition $targetDefinition,
        GCHelper $gcHelper,
        MigratorSettings $migratorSettings,
        ArchiveLister $archiveLister
    )
    {
        $this->sourceDefinition = $sourceDefinition;
        $this->targetDefinition = $targetDefinition;

        $this->gcHelper       = $gcHelper;
        $this->settings       = $migratorSettings;
        $this->archiveLister  = $archiveLister;

        $this->setupMigrators();
    }

    private function setupMigrators()
    {
        $this->siteMigrator           = new SiteMigrator($this->sourceDefinition, $this->targetDefinition, $this->gcHelper);
        $this->siteGoalMigrator       = new SiteGoalMigrator($this->sourceDefinition, $this->targetDefinition, $this->gcHelper, $this->siteMigrator);
        $this->siteUrlMigrator        = new SiteUrlMigrator($this->sourceDefinition, $this->targetDefinition, $this->gcHelper, $this->siteMigrator);
        $this->actionMigrator         = new ActionMigrator($this->sourceDefinition, $this->targetDefinition, $this->gcHelper);
        $this->visitMigrator          = new VisitMigrator($this->sourceDefinition, $this->targetDefinition, $this->gcHelper, $this->siteMigrator, $this->actionMigrator);
        $this->visitActionMigrator    = new LinkVisitActionMigrator($this->sourceDefinition, $this->targetDefinition, $this->gcHelper, $this->siteMigrator, $this->visitMigrator, $this->actionMigrator);
        $this->conversionMigrator     = new ConversionMigrator($this->sourceDefinition, $this->targetDefinition, $this->gcHelper, $this->siteMigrator, $this->visitMigrator, $this->actionMigrator, $this->visitActionMigrator);
        $this->conversionItemMigrator = new ConversionItemMigrator($this->sourceDefinition, $this->targetDefinition, $this->gcHelper, $this->siteMigrator, $this->visitMigrator, $this->actionMigrator);
        $this->archiveMigrator        = new ArchiveMigrator($this->sourceDefinition, $this->targetDefinition, $this->gcHelper, $this->siteMigrator, $this->archiveLister);
    }

    public function migrate()
    {
        $this->startTransaction();

        $this->migrateSite();

        $this->loadActions();

        if (!$this->settings->skipLogData) {
            $this->migrateLogVisits();
            $this->migrateLogVisitActions();
            $this->migrateLogVisitConversions();
        }

        if (!$this->settings->skipArchiveData) {
            $this->migrateArchives();
        }

        $this->commitTransaction();
    }

    private function startTransaction()
    {
        Log::info('Start transaction');
        $this->targetDefinition->getDbHelper()->startTransaction();
    }

    private function commitTransaction()
    {
        Log::info('Commit transaction');
        $this->targetDefinition->getDbHelper()->commitTransaction();
    }

    private function migrateSite()
    {
        Log::info('Migrating site and site config');

        $sourceDbHelper = $this->sourceDefinition->getDbHelper();
        $sourceSiteId = $this->sourceDefinition->getSiteId();

        $this->siteMigrator->migrate(
            $this->getBatchProvider(
                'SELECT * FROM ' . $sourceDbHelper->prefixTable('site') . ' WHERE idsite = ' . $sourceSiteId . ' ORDER BY idsite ASC'
            )
        );

        $this->siteGoalMigrator->migrate(
            $this->getBatchProvider(
                'SELECT * FROM ' . $sourceDbHelper->prefixTable('goal') . ' WHERE idsite = ' . $sourceSiteId . ' ORDER BY idsite ASC, idgoal ASC'
            )
        );

        $this->siteUrlMigrator->migrate(
            $this->getBatchProvider(
                'SELECT * FROM ' . $sourceDbHelper->prefixTable('site_url') . ' WHERE idsite = ' . $sourceSiteId . ' ORDER BY idsite ASC, url ASC'
            )
        );
    }

    private function loadActions()
    {
        Log::info('Loading existing actions');

        $this->actionMigrator->loadExistingActions();
    }

    private function migrateLogVisits()
    {
        Log::info('Migrating log data - visits');

        $query = 'SELECT * FROM ' . $this->sourceDefinition->getDbHelper()->prefixTable('log_visit') . ' WHERE idsite = ' . $this->sourceDefinition->getSiteId() . ' ORDER BY idvisit ASC';

        if ($this->settings->dateFrom) {
            $query .= ' AND `visit_last_action_time` >= \'' . $this->settings->dateFrom->format('Y-m-d') . '\'';
        }

        if ($this->settings->dateTo) {
            $query .= ' AND `visit_last_action_time` < \'' . $this->settings->dateTo->format('Y-m-d') . '\'';
        }

        $this->visitMigrator->migrate(
            $this->getBatchProvider($query)
        );
    }

    private function migrateLogVisitActions()
    {
        Log::info('Migrating log data - link visit action');

        $queries = $this->getLogVisitQueriesForLinkVisitAction();

        if (count($queries) > 0) {
            $this->visitActionMigrator->migrate($this->getBatchProvider($queries));
        }
    }

    private function migrateLogVisitConversions()
    {
        Log::info('Migrating log data - conversions and conversion items');

        $queries     = $this->getLogVisitQueriesForLogConversion();
        $itemQueries = $this->getLogVisitQueriesForLogConversionItem();

        if (count($queries) > 0) {
            $this->conversionMigrator->migrate($this->getBatchProvider($queries));
            $this->conversionItemMigrator->migrate($this->getBatchProvider($itemQueries));
        }
    }

    private function migrateArchives()
    {
        Log::info('Migrating archive data');

        $this->archiveMigrator->migrate($this->sourceDefinition->getSiteId(), $this->settings->dateFrom, $this->settings->dateTo);
    }

    private function getLogVisitQueriesFor($table, $orderBy = array())
    {
        $visitIdRanges = $this->visitMigrator->getIdRanges();

        if (count($visitIdRanges) > 0) {
            $baseQuery = "SELECT * FROM " . $this->sourceDefinition->getDbHelper()->prefixTable($table) . ' WHERE idvisit IN ';
            $queries   = array();


            foreach ($visitIdRanges as $range) {
                $newQuery = $baseQuery . ' (' . implode(', ', $range) . ')';

                if (count($orderBy) != 0) {
                    $newQuery .= ' ORDER BY ' . implode(',', $orderBy);
                }

                $queries[] = $newQuery;
            }

            return $queries;
        } else {
            return array();
        }
    }

    private function getLogVisitQueriesForLogConversion()
    {
        return $this->getLogVisitQueriesFor('log_conversion', array('idvisit', 'idgoal', 'buster'));
    }

    private function getLogVisitQueriesForLogConversionItem(){
        return $this->getLogVisitQueriesFor('log_conversion_item', array('idvisit', 'idorder', 'idaction_sku'));
    }

    private function getLogVisitQueriesForLinkVisitAction()
    {
        return $this->getLogVisitQueriesFor('log_link_visit_action', array('idlink_va'));
    }

    private function getBatchProvider($query)
    {
        return new BatchProvider($query, $this->sourceDefinition->getDbHelper(), $this->gcHelper, 10000);
    }
}
