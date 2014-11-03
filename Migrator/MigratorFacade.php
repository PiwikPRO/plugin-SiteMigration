<?php


namespace Piwik\Plugins\SiteMigration\Migrator;


use Piwik\Log;
use Piwik\Plugins\SiteMigration\DataProvider\BatchProvider;
use Piwik\Plugins\SiteMigration\Helper\DBHelper;
use Piwik\Plugins\SiteMigration\Helper\GCHelper;

class MigratorFacade
{
    /**
     * @var \Zend_Db_Adapter_Abstract
     */
    protected $fromDb;
    /**
     * @var \Zend_Db_Adapter_Abstract
     */
    protected $toDb;
    /**
     * @var DBHelper
     */
    protected $toDbHelper;
    /**
     * @var DBHelper
     */
    protected $fromDbHelper;
    /**
     * @var GCHelper
     */
    protected $gcHelper;
    /**
     * @var SiteMigrator
     */
    protected $siteMigrator;
    /**
     * @var SiteGoalMigrator
     */
    protected $siteGoalMigrator;
    /**
     * @var SiteUrlMigrator
     */
    protected $siteUrlMigrator;
    /**
     * @var ActionMigrator
     */
    protected $actionMigrator;
    /**
     * @var VisitMigrator
     */
    protected $visitMigrator;
    /**
     * @var LinkVisitActionMigrator
     */
    protected $visitActionMigrator;
    /**
     * @var ConversionMigrator
     */
    protected $conversionMigrator;
    /**
     * @var ConversionItemMigrator
     */
    protected $conversionItemMigrator;
    /**
     * @var ArchiveMigrator
     */
    protected $archiveMigrator;

    /**
     * @var MigratorSettings
     */
    protected $migratorSettings;

    function __construct($fromDb, $fromDbHelper, $toDb, $toDbHelper, $gcHelper, $migratorSettings)
    {
        $this->fromDb           = $fromDb;
        $this->fromDbHelper     = $fromDbHelper;
        $this->gcHelper         = $gcHelper;
        $this->migratorSettings = $migratorSettings;
        $this->toDb             = $toDb;
        $this->toDbHelper       = $toDbHelper;

        $this->setupMigrators();
    }

    protected function setupMigrators()
    {
        $this->siteMigrator           = new SiteMigrator($this->toDbHelper, $this->gcHelper);
        $this->siteGoalMigrator       = new SiteGoalMigrator($this->toDbHelper, $this->gcHelper, $this->siteMigrator);
        $this->siteUrlMigrator        = new SiteUrlMigrator($this->toDbHelper, $this->gcHelper, $this->siteMigrator);
        $this->actionMigrator         = new ActionMigrator($this->fromDbHelper, $this->toDbHelper);
        $this->visitMigrator          = new VisitMigrator($this->toDbHelper, $this->gcHelper, $this->siteMigrator, $this->actionMigrator);
        $this->visitActionMigrator    = new LinkVisitActionMigrator($this->toDbHelper, $this->gcHelper, $this->siteMigrator, $this->visitMigrator, $this->actionMigrator);
        $this->conversionMigrator     = new ConversionMigrator($this->toDbHelper, $this->gcHelper, $this->siteMigrator, $this->visitMigrator, $this->actionMigrator, $this->visitActionMigrator);
        $this->conversionItemMigrator = new ConversionItemMigrator($this->toDbHelper, $this->gcHelper, $this->siteMigrator, $this->visitMigrator, $this->actionMigrator);
        $this->archiveMigrator        = new ArchiveMigrator($this->fromDbHelper, $this->toDbHelper, $this->siteMigrator);
    }

    public function startTransaction()
    {
        Log::warning('Start transaction');
        $this->toDb->beginTransaction();
    }

    public function commitTransaction()
    {
        Log::warning('Commit transaction');
        $this->toDb->commit();
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

    protected function migrateSiteConfig()
    {
        Log::warning('Migrating site config');

        $this->siteMigrator->migrate(
            $this->getBatchProvider(
                'SELECT * FROM ' . $this->fromDbHelper->prefixTable('site') . ' WHERE idsite = ' . $this->migratorSettings->idSite
            )
        );

        $this->siteGoalMigrator->migrate(
            $this->getBatchProvider(
                'SELECT * FROM ' . $this->fromDbHelper->prefixTable('goal') . ' WHERE idsite = ' . $this->migratorSettings->idSite
            )
        );

        $this->siteUrlMigrator->migrate(
            $this->getBatchProvider(
                'SELECT * FROM ' . $this->fromDbHelper->prefixTable('site_url') . ' WHERE idsite = ' . $this->migratorSettings->idSite
            )
        );
    }

    protected function loadActions()
    {
        Log::warning('Loading existing actions');

        $this->actionMigrator->loadExistingActions();
    }

    protected function migrateLogVisits()
    {
        Log::warning('Migrating log data - visits');

        $query = 'SELECT * FROM ' . $this->fromDbHelper->prefixTable('log_visit') . ' WHERE idsite = ' . $this->migratorSettings->idSite;

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

    protected function migrateLogVisitActions()
    {
        Log::warning('Migrating log data - link visit action');

        $queries = $this->getLogVisitQueriesFor('log_link_visit_action');

        if (count($queries) > 0) {
            $this->visitActionMigrator->migrate($this->getBatchProvider($queries));
        }
    }

    protected function migrateLogVisitConversions()
    {
        Log::warning('Migrating log data - conversions and conversion items');

        $queries     = $this->getLogVisitQueriesFor('log_conversion');
        $itemQueries = $this->getLogVisitQueriesFor('log_conversion_item');

        if (count($queries) > 0) {
            $this->conversionMigrator->migrate($this->getBatchProvider($queries));
            $this->conversionItemMigrator->migrate($this->getBatchProvider($itemQueries));
        }
    }

    protected function migrateArchives()
    {
        Log::warning('Migrating archive data');

        $archives = $this->archiveMigrator->getArchiveList($this->migratorSettings->dateFrom, $this->migratorSettings->dateTo);

        foreach ($archives as $archive) {
            Log::info('Migrating archive ' . $archive);
            $this->archiveMigrator->migrateArchive($archive, $this->migratorSettings->idSite);
        }
    }

    protected function getLogVisitQueriesFor($table)
    {
        $visitIdRanges = $this->visitMigrator->getIdRanges();

        if (count($visitIdRanges) > 0) {
            $baseQuery = "SELECT * FROM " . $this->fromDbHelper->prefixTable($table) . ' WHERE idvisit IN ';
            $queries   = array();


            foreach ($visitIdRanges as $range) {
                $queries[] = $baseQuery . ' (' . implode(', ', $range) . ')';
            }

            return $queries;
        } else {
            return array();
        }
    }

    protected function getBatchProvider($query)
    {
        return new BatchProvider($query, $this->fromDbHelper, $this->gcHelper, 10000);
    }

}