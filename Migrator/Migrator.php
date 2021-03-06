<?php
/**
 * Piwik PRO -  Premium functionality and enterprise-level support for Piwik Analytics
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SiteMigration\Migrator;

use Piwik\Log;
use Piwik\Plugins\SiteMigration\DataProvider\BatchProvider;
use Piwik\Plugins\SiteMigration\Helper\DBHelper;
use Piwik\Plugins\SiteMigration\Helper\GCHelper;
use Piwik\Plugins\SiteMigration\Migrator\Archive\ArchiveLister;

class Migrator
{
    /**
     * @var DBHelper
     */
    private $targetDbHelper;
    /**
     * @var DBHelper
     */
    private $sourceDbHelper;
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

    public function __construct(DBHelper $sourceDbHelper, DBHelper $targetDbHelper, GCHelper $gcHelper, MigratorSettings $migratorSettings, ArchiveLister $archiveLister)
    {
        $this->sourceDbHelper = $sourceDbHelper;
        $this->targetDbHelper = $targetDbHelper;
        $this->gcHelper       = $gcHelper;
        $this->settings       = $migratorSettings;
        $this->archiveLister  = $archiveLister;

        $this->setupMigrators();
    }

    private function setupMigrators()
    {
        $this->siteMigrator           = new SiteMigrator($this->targetDbHelper, $this->gcHelper);
        $this->siteGoalMigrator       = new SiteGoalMigrator($this->targetDbHelper, $this->gcHelper, $this->siteMigrator);
        $this->siteUrlMigrator        = new SiteUrlMigrator($this->targetDbHelper, $this->gcHelper, $this->siteMigrator);
        $this->actionMigrator         = new ActionMigrator($this->sourceDbHelper, $this->targetDbHelper);
        $this->visitMigrator          = new VisitMigrator($this->targetDbHelper, $this->gcHelper, $this->siteMigrator, $this->actionMigrator);
        $this->visitActionMigrator    = new LinkVisitActionMigrator($this->targetDbHelper, $this->gcHelper, $this->siteMigrator, $this->visitMigrator, $this->actionMigrator);
        $this->conversionMigrator     = new ConversionMigrator($this->targetDbHelper, $this->gcHelper, $this->siteMigrator, $this->visitMigrator, $this->actionMigrator, $this->visitActionMigrator);
        $this->conversionItemMigrator = new ConversionItemMigrator($this->targetDbHelper, $this->gcHelper, $this->siteMigrator, $this->visitMigrator, $this->actionMigrator);
        $this->archiveMigrator        = new ArchiveMigrator($this->sourceDbHelper, $this->targetDbHelper, $this->siteMigrator, $this->archiveLister);
    }

    public function migrate()
    {
        $this->startTransaction();

        $this->migrateSiteConfig();

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
        $this->targetDbHelper->startTransaction();
    }

    private function commitTransaction()
    {
        Log::info('Commit transaction');
        $this->targetDbHelper->commitTransaction();
    }

    private function migrateSiteConfig()
    {
        Log::info('Migrating site config');

        $this->siteMigrator->migrate(
            $this->getBatchProvider(
                'SELECT * FROM ' . $this->sourceDbHelper->prefixTable('site') . ' WHERE idsite = ' . $this->settings->idSite
            )
        );

        $this->siteGoalMigrator->migrate(
            $this->getBatchProvider(
                'SELECT * FROM ' . $this->sourceDbHelper->prefixTable('goal') . ' WHERE idsite = ' . $this->settings->idSite
            )
        );

        $this->siteUrlMigrator->migrate(
            $this->getBatchProvider(
                'SELECT * FROM ' . $this->sourceDbHelper->prefixTable('site_url') . ' WHERE idsite = ' . $this->settings->idSite
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

        $query = 'SELECT * FROM ' . $this->sourceDbHelper->prefixTable('log_visit') . ' WHERE idsite = ' . $this->settings->idSite;

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

        $queries = $this->getLogVisitQueriesFor('log_link_visit_action');

        if (count($queries) > 0) {
            $this->visitActionMigrator->migrate($this->getBatchProvider($queries));
        }
    }

    private function migrateLogVisitConversions()
    {
        Log::info('Migrating log data - conversions and conversion items');

        $queries     = $this->getLogVisitQueriesFor('log_conversion');
        $itemQueries = $this->getLogVisitQueriesFor('log_conversion_item');

        if (count($queries) > 0) {
            $this->conversionMigrator->migrate($this->getBatchProvider($queries));
            $this->conversionItemMigrator->migrate($this->getBatchProvider($itemQueries));
        }
    }

    private function migrateArchives()
    {
        Log::info('Migrating archive data');

        $this->archiveMigrator->migrate($this->settings->idSite, $this->settings->dateFrom, $this->settings->dateTo);
    }

    private function getLogVisitQueriesFor($table)
    {
        $visitIdRanges = $this->visitMigrator->getIdRanges();

        if (count($visitIdRanges) > 0) {
            $baseQuery = "SELECT * FROM " . $this->sourceDbHelper->prefixTable($table) . ' WHERE idvisit IN ';
            $queries   = array();


            foreach ($visitIdRanges as $range) {
                $queries[] = $baseQuery . ' (' . implode(', ', $range) . ')';
            }

            return $queries;
        } else {
            return array();
        }
    }

    private function getBatchProvider($query)
    {
        return new BatchProvider($query, $this->sourceDbHelper, $this->gcHelper, 10000);
    }
}
