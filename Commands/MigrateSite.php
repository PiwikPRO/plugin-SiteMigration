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
        $self = $this;

        // Set memory limit to off
        @ini_set('memory_limit', -1);
        Piwik::doAsSuperUser(function() use ($input, $output, $self){
            $settings = new MigratorSettings();
            $settings->idSite = $input->getArgument('idSite');
            $settings->site = $self->getSite($settings->idSite);
            $settings->dateFrom = $input->getOption('date-from') ? new \DateTime($input->getOption('date-from')) : null;
            $settings->dateTo = $input->getOption('date-to') ? new \DateTime($input->getOption('date-to')) : null;
            $settings->skipArchiveData = $input->getOption('skip-archive-data');
            $settings->skipLogData = $input->getOption('skip-log-data');

            $config = Db::getDatabaseConfig();
            $startTime = microtime(true);

            $self->createTargetDatabaseConfig($input, $output, $config);

            $tmpConfig = $config;
            $sourceDb = Db::get();
            try {
                $targetDb = @Db\Adapter::factory($config['adapter'], $tmpConfig);
            } catch (\Exception $e) {
                throw new \RuntimeException('Unable to connect to the target database: ' . $e->getMessage(), 0, $e);
            }

            $sourceDbHelper = new DBHelper($sourceDb, Db::getDatabaseConfig());

            $migratorFacade = new Migrator(
                $sourceDbHelper,
                new DBHelper($targetDb, $config),
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


    public function getSite($idSite)
    {
        if (!Site::getSite($idSite)) {
            throw new \InvalidArgumentException('idSite is not a valid, no such site found');
        }

        return Db::get()->fetchRow(
            "SELECT * FROM " . Common::prefixTable("site") . " WHERE idsite = ?",
            $idSite
        );
    }

    public function createTargetDatabaseConfig(InputInterface $input, OutputInterface $output, &$config)
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

    public function ensureOptionsIsProvided($optionName, InputInterface $input, OutputInterface $output, $question, $validator, $attempts = false, $default = null, $hidden = false)
    {
        if (!$input->getOption($optionName)) {
            return $this->askAndValidate($output, $question, $validator, $attempts, $default, $hidden);
        }

        return $input->getOption($optionName);
    }

    protected function askAndValidate(
        OutputInterface $output,
        $question,
        $validator,
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
