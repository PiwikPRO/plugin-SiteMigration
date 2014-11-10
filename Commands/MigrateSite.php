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
use Piwik\Plugins\SiteMigration\Migrator\MigratorFacade;
use Piwik\Plugins\SiteMigration\Migrator\MigratorSettings;
use Piwik\Site;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateSite extends ConsoleCommand
{
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
        $this->addOption('db-prefix', null, InputOption::VALUE_REQUIRED, 'Destination database table prefix', Common::prefixTable(''));
        $this->addOption('db-port', null, InputOption::VALUE_REQUIRED, 'Destination database port', '3306');

        /**
         * Visit query options
         */
        $this->addOption('date-from', 'F', InputOption::VALUE_REQUIRED, 'Start date from which data should be migrated');
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

        $migratorSettings = new MigratorSettings();
        $migratorSettings->idSite = $input->getArgument('idSite');
        $migratorSettings->site = $this->getSite($migratorSettings->idSite);
        $migratorSettings->dateFrom = ($input->getOption('date-from')) ? new \DateTime($input->getOption('date-from')) : null;
        $migratorSettings->dateTo = ($input->getOption('date-to')) ? new \DateTime($input->getOption('date-to')) : null;
        $config = Db::getDatabaseConfig();
        $startTime = microtime(true);

        try {
            $this->createDestinationDatabaseConfig($input, $output, $config);
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return;
        }

        $tmpConfig = $config;
        $sourceDb = Db::get();
        try {
            $targetDb = @Db\Adapter::factory($config['adapter'], $tmpConfig);
        } catch (\Exception $e) {
            throw new \RuntimeException('Unable to connect to the target database: ' . $e->getMessage(), 0, $e);
        }

        if ($output->getVerbosity() == OutputInterface::VERBOSITY_VERBOSE) {
            Log::getInstance()->setLogLevel(Log::INFO);
        }

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
            Log::getInstance()->setLogLevel(Log::VERBOSE);
        }

        $migratorFacade = new MigratorFacade(
            new DBHelper($sourceDb, Db::getDatabaseConfig()),
            new DBHelper($targetDb, $config),
            GCHelper::getInstance(),
            $migratorSettings
        );

        $migratorFacade->migrate();

        $endTime = microtime(true);

        $output->writeln(sprintf(PHP_EOL . '<comment>Time taken: %01.2f sec</comment>', $endTime - $startTime));
        $output->writeln(sprintf(
            '<comment>Peak memory usage: %01.2f MB</comment>',
            memory_get_peak_usage(true) / 1048576
        ));
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

    protected function createDestinationDatabaseConfig(InputInterface $input, OutputInterface $output, &$config)
    {

        $notNullValidator = function ($answer) {
            if (strlen(trim($answer)) == 0) {
                throw new \InvalidArgumentException('This value should not be empty');
            }

            return $answer;
        };

        $dummyValidator = function ($answer) {
            return $answer;
        };

        $config['host'] = $this->ensureOptionsIsProvided(
            'db-host',
            $input,
            $output,
            'Please provide the destination database host',
            $notNullValidator,
            false,
            'localhost'
        );

        $config['username'] = $this->ensureOptionsIsProvided(
            'db-username',
            $input,
            $output,
            'Please provide the destination database username',
            $notNullValidator
        );

        $config['password'] = $this->ensureOptionsIsProvided(
            'db-password',
            $input,
            $output,
            'Please provide the destination database password',
            $dummyValidator,
            false,
            null,
            true
        );

        $config['dbname'] = $this->ensureOptionsIsProvided(
            'db-name',
            $input,
            $output,
            'Please provide the destination database name',
            $notNullValidator
        );

        $config['port'] = $input->getOption('db-port');
        $config['tables_prefix'] = $input->getOption('db-prefix');
    }

    public function ensureOptionsIsProvided($optionName, InputInterface $input, OutputInterface $output, $question, callable $validator, $attempts = false, $default = null, $hidden = false)
    {
        if (!$input->getOption($optionName)) {
            return $this->askAndValidate($output, $question, $validator, $attempts, $default, $hidden);
        }

        return $input->getOption($optionName);
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
        $dialog = $this->getHelperSet()->get('dialog');
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
