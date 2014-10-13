<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\SiteMigration\Commands;

use Piwik\Common;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\SiteMigration\DataProvider\BatchProvider;
use Piwik\Plugins\SiteMigration\Helper\DBHelper;
use Piwik\Plugins\SiteMigration\Helper\GCHelper;
use Piwik\Plugins\SiteMigration\Migrator\ActionMigrator;
use Piwik\Plugins\SiteMigration\Migrator\ArchiveMigrator;
use Piwik\Plugins\SiteMigration\Migrator\ConversionItemMigrator;
use Piwik\Plugins\SiteMigration\Migrator\ConversionMigrator;
use Piwik\Plugins\SiteMigration\Migrator\LinkVisitActionMigrator;
use Piwik\Plugins\SiteMigration\Migrator\SiteGoalMigrator;
use Piwik\Plugins\SiteMigration\Migrator\SiteMigrator;
use Piwik\Plugins\SiteMigration\Migrator\SiteUrlMigrator;
use Piwik\Plugins\SiteMigration\Migrator\VisitMigrator;
use Piwik\Plugins\SiteMigration\Model\IdMapCollection;
use Piwik\Site;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Piwik\Plugins\SiteMigration\Migrator\SiteConfigMigrator;


class MigrateSite extends ConsoleCommand
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
     * @var IdMapCollection
     */
    protected $idMapCollection;


    protected function configure()
    {
        $this->setName('migration:site');
        $this->setDescription('Migrate site between Piwik instances');
        $this->addArgument('idSite', InputArgument::REQUIRED, 'Site id');

        /**
         * Migration options
         */
        $this->addOption('skip-archive-data', null, InputOption::VALUE_NONE, 'Skip migration of archived data');
        $this->addOption('skip-log-data', null, InputOption::VALUE_NONE, 'Skip migration of log data');

        /**
         * Database options
         */
        $this->addOption('db-host', 'H', InputOption::VALUE_REQUIRED, 'Destination database host');
        $this->addOption('db-username', 'U', InputOption::VALUE_REQUIRED, 'Destination database username');
        $this->addOption('db-password', 'P', InputOption::VALUE_REQUIRED, 'Destination database password');
        $this->addOption('db-name', 'N', InputOption::VALUE_REQUIRED, 'Destination database name');
        $this->addOption(
            'db-prefix',
            null,
            InputOption::VALUE_REQUIRED,
            'Destination database table prefix',
            Common::prefixTable('')
        );
        $this->addOption('db-port', null, InputOption::VALUE_REQUIRED, 'Destination database port', '3306');

        /**
         * Visit query options
         */
        $this->addOption(
            'date-from',
            'F',
            InputOption::VALUE_REQUIRED,
            'Start date from which data should be migrated'
        );
        $this->addOption('date-to', 'T', InputOption::VALUE_REQUIRED, 'Start date from which data should be migrated');

        /**
         * Site id options
         */
        $this->addOption(
            'new-id-site',
            'I',
            InputOption::VALUE_REQUIRED,
            'New site id, if provided site config will not be migrated, log and archive data will be copied into existing site'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /**
         * Set memory limit to off
         */
        @ini_set('memory_limit', -1);
        Piwik::setUserHasSuperUserAccess();

        $idSite       = $input->getArgument('idSite');
        $site         = $this->getSite($idSite);
        $this->fromDb = Db::get();
        $config       = Db::getDatabaseConfig();

        try {
            $this->createDestinationDatabaseConfig($input, $output, $config);
        } catch (\RuntimeException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return;
        }

        $tmpConfig             = $config;
        $this->toDb            = @Db\Adapter::factory($config['adapter'], $tmpConfig);
        $this->idMapCollection = new IdMapCollection();
        $this->toDbHelper      = new DBHelper($this->toDb, $config);
        $this->fromDbHelper    = new DBHelper($this->fromDb, Db::getDatabaseConfig());
        $startTime             = microtime(true);

        $this->toDb->beginTransaction();

        $gcHelper = GCHelper::getInstance();

        $output->writeln(sprintf('<info>Migrating site: %s - %s</info>', $idSite, $site['name']));

        $output->writeln('<info>Migrating settings...</info>');
        $output->writeln('<comment> - Main settings</comment>');

        $siteMigrator = new SiteMigrator($this->toDbHelper, $gcHelper);
        $siteMigrator->migrate(
            new BatchProvider(
                'SELECT * FROM ' . $this->fromDbHelper->prefixTable('site') . ' WHERE idsite = ' . $idSite,
                $this->fromDbHelper,
                $gcHelper,
                10
            )
        );

        $output->writeln('<comment> - Goals</comment>');

        $siteGoalMigrator = new SiteGoalMigrator($this->toDbHelper, $gcHelper, $siteMigrator);
        $siteGoalMigrator->migrate(
            new BatchProvider(
                'SELECT * FROM ' . $this->fromDbHelper->prefixTable('goal') . ' WHERE idsite = ' . $idSite,
                $this->fromDbHelper,
                $gcHelper,
                100
            )
        );

        $output->writeln('<comment> - Site url</comment>');

        $siteUrlMigrator = new SiteUrlMigrator($this->toDbHelper, $gcHelper, $siteMigrator);
        $siteUrlMigrator->migrate(
            new BatchProvider(
                'SELECT * FROM ' . $this->fromDbHelper->prefixTable('site_url') . ' WHERE idsite = ' . $idSite,
                $this->fromDbHelper,
                $gcHelper,
                100
            )
        );

        $output->writeln('<info>Load existing actions... </info>');
        $actionMigrator  = new ActionMigrator($this->fromDbHelper, $this->toDbHelper);
        $actionMigrator->loadExistingActions();

        $output->writeln('<info>Migrating visits... </info>');
        $visitorMigrator = new VisitMigrator($this->toDbHelper, $gcHelper, $siteMigrator, $actionMigrator);
        $visitorMigrator->migrate(
            new BatchProvider(
                'SELECT * FROM ' . $this->fromDbHelper->prefixTable('log_visit') . ' WHERE idsite = ' . $idSite,
                $this->fromDbHelper,
                $gcHelper,
                100
            )
        );

        $output->writeln('<info>Migrating visit actions... </info>');
        $visitActionMigrator = new LinkVisitActionMigrator($this->toDbHelper, $gcHelper, $siteMigrator, $visitorMigrator, $actionMigrator);
        $baseQuery           = "SELECT * FROM " . $this->fromDbHelper->prefixTable('log_link_visit_action') . ' WHERE idvisit IN ';
        $queries             = array();
        $visitIdRanges       = $visitorMigrator->getIdRanges();

        foreach ($visitIdRanges as $range) {
            $queries[] = $baseQuery . ' (' . implode(', ', $range) . ')';
        }
        $visitActionMigrator->migrate(new BatchProvider($queries, $this->fromDbHelper, $gcHelper));

        $output->writeln('<info>Migrating conversions with conversion items... </info>');

        $conversionMigrator      = new ConversionMigrator($this->toDbHelper, $gcHelper, $siteMigrator, $visitorMigrator, $actionMigrator, $visitActionMigrator);
        $conversionItemMigrator  = new ConversionItemMigrator($this->toDbHelper, $gcHelper, $siteMigrator, $visitorMigrator, $actionMigrator);
        $baseConversionQuery     = "SELECT * FROM " . $this->fromDbHelper->prefixTable('log_conversion') . ' WHERE idvisit IN ';
        $baseConversionItemQuery = "SELECT * FROM " . $this->fromDbHelper->prefixTable('log_conversion_item') . ' WHERE idvisit IN ';
        $conversionQueries       = array();
        $conversionItemQueries   = array();
        $visitIdRanges           = $visitorMigrator->getIdRanges();

        foreach ($visitIdRanges as $range) {
            $conversionQueries[]     = $baseConversionQuery . ' (' . implode(', ', $range) . ')';
            $conversionItemQueries[] = $baseConversionItemQuery . ' (' . implode(', ', $range) . ')';
        }

        $conversionMigrator->migrate(new BatchProvider($conversionQueries, $this->fromDbHelper, $gcHelper));
        $conversionItemMigrator->migrate(new BatchProvider($conversionItemQueries, $this->fromDbHelper, $gcHelper));

        $output->writeln('<info>Closing transaction</info>');
        $this->toDb->commit();

        $endTime = microtime(true);

        $output->writeln(sprintf(PHP_EOL . '<comment>Time taken %01.2f sec</comment>', $endTime - $startTime));
        $output->writeln(sprintf('<comment>Memory allocated %01.2f MB</comment>', memory_get_usage(true) / 1048576));
        $output->writeln(
            sprintf('<comment>Memory allocated peak %01.2f MB</comment>', memory_get_peak_usage(true) / 1048576)
        );
    }




    protected function getSite($idSite)
    {
        if (!Site::getSite($idSite)) {
            throw new \InvalidArgumentException('idSite is not a valid, no such site found');
        }

        return Db::get()->fetchRow(
            "SELECT * FROM " . Common::prefixTable("site") . " WHERE idsite = ?",
            $idSite
        );
    }

    protected function migrateArchives(InputInterface $input, OutputInterface $output, $idSite)
    {
        $output->writeln('<info>Migrating archives...</info>');
        $archiveMigrator = new ArchiveMigrator($this->fromDbHelper, $this->toDbHelper, $this->idMapCollection);
        $archives        = $archiveMigrator->getArchiveList();
        foreach ($archives as $archive) {
            $output->writeln('<comment> - Migrating ' . $archive . '</comment>');
            $archiveMigrator->migrateArchive($archive, $idSite);
        }
    }

    protected function createDestinationDatabaseConfig(InputInterface $input, OutputInterface $output, &$config)
    {

        $notNullValidator = function ($answer) {
            if (strlen(trim($answer)) == 0) {
                throw new \RuntimeException('This value should not be empty');
            }

            return $answer;
        };

        $dummyValidator = function ($answer) {
            return $answer;
        };


        if (!$input->getOption('db-host')) {
            $config['host'] = $this->askAndValidate(
                $output,
                'Please provide the destination database host',
                $notNullValidator,
                false,
                'localhost'
            );
        } else {
            $config['host'] = $input->getOption('db-host');
        }

        if (!$input->getOption('db-username')) {
            $config['username'] = $this->askAndValidate(
                $output,
                'Please provide the destination database username',
                $notNullValidator
            );
        } else {
            $config['username'] = $input->getOption('db-username');
        }

        if (!$input->getOption('db-password')) {
            $config['password'] = $this->askAndValidate(
                $output,
                'Please provide the destination database password',
                $dummyValidator,
                false,
                null,
                true
            );
        } else {
            $config['password'] = $input->getOption('db-password');
        }

        if (!$input->getOption('db-name')) {
            $config['dbname'] = $this->askAndValidate(
                $output,
                'Please provide the destination database name',
                $notNullValidator
            );
        } else {
            $config['dbname'] = $input->getOption('db-name');
        }

        $config['port']          = $input->getOption('db-port');
        $config['tables_prefix'] = $input->getOption('db-prefix');
    }

    protected function askAndValidate(
        OutputInterface $output,
        $question,
        callable $validator,
        $attempts = false,
        $default = null,
        $hidden = false
    )
    {
        /**
         * @var $dialog DialogHelper
         */
        $dialog   = $this->getHelperSet()->get('dialog');
        $question = '<question>' . $question . (($default) ? " [$default]" : '') . ':</question> ';

        if (!$hidden) {
            return $dialog->askAndValidate(
                $output,
                $question,
                $validator,
                $attempts,
                $default
            );
        } else {
            return $dialog->askHiddenResponseAndValidate(
                $output,
                $question,
                $validator,
                $attempts,
                $default
            );
        }
    }
}
