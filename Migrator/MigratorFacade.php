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
use Piwik\Plugins\SiteMigration\Helper\DBHelper;
use Piwik\Plugins\SiteMigration\Helper\GCHelper;

class MigratorFacade
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
    private $migratorSettings;

    public function __construct(DBHelper $sourceDbHelper, DBHelper $targetDbHelper, GCHelper $gcHelper, MigratorSettings $migratorSettings)
    {
        $this->sourceDbHelper   = $sourceDbHelper;
        $this->targetDbHelper   = $targetDbHelper;
        $this->gcHelper         = $gcHelper;
        $this->migratorSettings = $migratorSettings;

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
        $this->archiveMigrator        = new ArchiveMigrator($this->sourceDbHelper, $this->targetDbHelper, $this->siteMigrator);
    }

    public function migrate()
    {
        $this->startTransaction();

        if (!$this->migratorSettings->newIdSite) {
            $this->migrateSiteConfig();
        } else {
            $this->siteMigrator->addNewId($this->migratorSettings->idSite, $this->migratorSettings->newIdSite);
        }

        $this->loadActions();

        if (!$this->migratorSettings->skipLogData) {
            $this->migrateLogVisits();
            $this->migrateLogVisitActions();
            $this->migrateLogVisitConversions();
        }

        if (!$this->migratorSettings->skipArchiveData) {
            $this->migrateArchives();
        }

        $this->commitTransaction();
    }

    private function startTransaction()
    {
        Log::warning('Start transaction');
        $this->targetDbHelper->startTransaction();
    }

    private function commitTransaction()
    {
        Log::warning('Commit transaction');
        $this->targetDbHelper->commitTransaction();
    }

    private function migrateSiteConfig()
    {
        Log::warning('Migrating site config');

        $this->siteMigrator->migrate(
            $this->getBatchProvider(
                'SELECT * FROM ' . $this->sourceDbHelper->prefixTable('site') . ' WHERE idsite = ' . $this->migratorSettings->idSite
            )
        );

        $this->siteGoalMigrator->migrate(
            $this->getBatchProvider(
                'SELECT * FROM ' . $this->sourceDbHelper->prefixTable('goal') . ' WHERE idsite = ' . $this->migratorSettings->idSite
            )
        );

        $this->siteUrlMigrator->migrate(
            $this->getBatchProvider(
                'SELECT * FROM ' . $this->sourceDbHelper->prefixTable('site_url') . ' WHERE idsite = ' . $this->migratorSettings->idSite
            )
        );
    }

    private function loadActions()
    {
        Log::warning('Loading existing actions');

        $this->actionMigrator->loadExistingActions();
    }

    private function migrateLogVisits()
    {
        Log::warning('Migrating log data - visits');

        $query = 'SELECT * FROM ' . $this->sourceDbHelper->prefixTable('log_visit') . ' WHERE idsite = ' . $this->migratorSettings->idSite;

        if ($this->migratorSettings->dateFrom) {
            $query .= ' AND `visit_last_action_time` >= \'' . $this->migratorSettings->dateFrom->format('Y-m-d') . '\'';
        }

        if ($this->migratorSettings->dateTo) {
            $query .= ' AND `visit_last_action_time` < \'' . $this->migratorSettings->dateTo->format('Y-m-d') . '\'';
        }

        $this->visitMigrator->migrate(
            $this->getBatchProvider($query)
        );
    }

    private function migrateLogVisitActions()
    {
        Log::warning('Migrating log data - link visit action');

        $queries = $this->getLogVisitQueriesFor('log_link_visit_action');

        if (count($queries) > 0) {
            $this->visitActionMigrator->migrate($this->getBatchProvider($queries));
        }
    }

    private function migrateLogVisitConversions()
    {
        Log::warning('Migrating log data - conversions and conversion items');

        $queries     = $this->getLogVisitQueriesFor('log_conversion');
        $itemQueries = $this->getLogVisitQueriesFor('log_conversion_item');

        if (count($queries) > 0) {
            $this->conversionMigrator->migrate($this->getBatchProvider($queries));
            $this->conversionItemMigrator->migrate($this->getBatchProvider($itemQueries));
        }
    }

    private function migrateArchives()
    {
        Log::warning('Migrating archive data');

        $archives = $this->archiveMigrator->getArchiveList($this->migratorSettings->dateFrom, $this->migratorSettings->dateTo);

        foreach ($archives as $archive) {
            Log::info('Migrating archive ' . $archive);
            $this->archiveMigrator->migrateArchive($archive, $this->migratorSettings->idSite);
        }
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
