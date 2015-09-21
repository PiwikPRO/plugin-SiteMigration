<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SiteMigration\Commands;

use Piwik\Common;
use Piwik\Db;
use Piwik\Log;
use Piwik\Piwik;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\SiteMigration\Helper\DBHelper;
use Piwik\Plugins\SiteMigration\Helper\GCHelper;
use Piwik\Plugins\SiteMigration\Migrator\Archive\ArchiveLister;
use Piwik\Plugins\SiteMigration\Migrator\Migrator;
use Piwik\Plugins\SiteMigration\Migrator\MigratorSettings;
use Piwik\Site;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateSite extends ConsoleCommand
{
    protected static $DB_CONFIG_MAPPING = array(
        'host' => 'db-host',
        'username' => 'db-username',
        'password' => 'db-password',
        'dbname' => 'db-name',
        'port' => 'db-port',
        'tables_prefix' => 'db-prefix'
    );

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
        $this->addOption('db-prefix', null, InputOption::VALUE_OPTIONAL, 'Destination database table prefix');
        $this->addOption('db-port', null, InputOption::VALUE_REQUIRED, 'Destination database port', '3306');

        /**
         * Visit query options
         */
        $this->addOption('date-from', 'F', InputOption::VALUE_REQUIRED, 'Start date from which data should be migrated');
        $this->addOption('date-to', 'T', InputOption::VALUE_REQUIRED, 'Start date from which data should be migrated');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Set memory limit to off
        @ini_set('memory_limit', -1);
        Piwik::doAsSuperUser(function() use ($input, $output){
            $settings = new MigratorSettings();
            $settings->idSite = $input->getArgument('idSite');
            $settings->site = $this->getSite($settings->idSite);
            $settings->dateFrom = $input->getOption('date-from') ? new \DateTime($input->getOption('date-from')) : null;
            $settings->dateTo = $input->getOption('date-to') ? new \DateTime($input->getOption('date-to')) : null;
            $settings->skipArchiveData = $input->getOption('skip-archive-data');
            $settings->skipLogData = $input->getOption('skip-log-data');

            $localConfig = Db::getDatabaseConfig();
            $startTime = microtime(true);

            $targetConfig = $this->createTargetDatabaseConfig($input, $output, $localConfig);

            $sourceDb = Db::get();
            try {
                $tmpTargetConfig = $targetConfig; //The factory removes some necessary keys
                $targetDb = @Db\Adapter::factory($targetConfig['adapter'], $tmpTargetConfig);
            } catch (\Exception $e) {
                throw new \RuntimeException('Unable to connect to the target database: ' . $e->getMessage(), 0, $e);
            }

            $sourceDbHelper = new DBHelper($sourceDb, $localConfig);

            $migratorFacade = new Migrator(
                $sourceDbHelper,
                new DBHelper($targetDb, $targetConfig),
                GCHelper::getInstance(),
                $settings,
                new ArchiveLister($sourceDbHelper)
            );

            $migratorFacade->migrate();

            $endTime = microtime(true);

            Log::debug(sprintf('Time taken: %01.2f sec', $endTime - $startTime));
            Log::debug(sprintf('Peak memory usage: %01.2f MB', memory_get_peak_usage(true) / 1048576));
        });
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

    private function createTargetDatabaseConfig(InputInterface $input, OutputInterface $output, $baseConfig)
    {
        foreach (static::$DB_CONFIG_MAPPING as $configKey => $configParam) {
            $option = $input->getOption($configParam);
            if ($option) {
                $baseConfig[$configKey] = $option;
            };
        }

        return $baseConfig;
    }
}
