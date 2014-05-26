<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\SiteMigrator\Commands;

use Piwik\Common;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\SiteMigrator\Helper\DBHelper;
use Piwik\Plugins\SiteMigrator\Migrator\ActionMigrator;
use Piwik\Plugins\SiteMigrator\Migrator\ArchiveMigrator;
use Piwik\Plugins\SiteMigrator\Migrator\ConversionMigrator;
use Piwik\Plugins\SiteMigrator\Migrator\LinkVisitActionMigrator;
use Piwik\Plugins\SiteMigrator\Migrator\VisitMigrator;
use Piwik\Plugins\SiteMigrator\Model\IdMapCollection;
use Piwik\Site;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Piwik\Plugins\SiteMigrator\Migrator\SiteConfigMigrator;


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
        $this->setName('migrate:site');
        $this->setDescription('Migrate site between Piwik instances');
        $this->addArgument('idSite', InputArgument::REQUIRED, 'Site id');
        $this->addOption('skip-archived', null, InputOption::VALUE_NONE, 'Skip migration of archived data');
        $this->addOption('skip-raw', null, InputOption::VALUE_NONE, 'Skip migration of raw data');

        $this->addOption('host', 'H', InputOption::VALUE_REQUIRED, 'Destination database host');
        $this->addOption('username', 'U', InputOption::VALUE_REQUIRED, 'Destination database username');
        $this->addOption('password', 'P', InputOption::VALUE_REQUIRED, 'Destination database password');
        $this->addOption('dbname', 'N', InputOption::VALUE_REQUIRED, 'Destination database name');
        $this->addOption(
            'prefix',
            null,
            InputOption::VALUE_REQUIRED,
            'Destination database table prefix',
            Common::prefixTable('')
        );
        $this->addOption('port', null, InputOption::VALUE_REQUIRED, 'Destination database port', '3306');

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        @ini_set('memory_limit', -1);
        gc_enable();

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

        $output->writeln(sprintf('<info>Migrating site: %s - %s</info>', $idSite, $site['name']));

        $this->migrateSettings($input, $output, $idSite);

        if (!$input->getOption('skip-raw')) {
            $this->migrateRawData(
                $input,
                $output,
                $idSite
            );
        }

        if (!$input->getOption('skip-archived')) {
            $this->migrateArchives($input, $output, $idSite);
        }


        $output->writeln('<info>Closing transaction</info>');
        $this->toDb->commit();

        $endTime = microtime(true);

        $output->writeln(sprintf(PHP_EOL . '<comment>Time taken %01.2f sec</comment>', $endTime - $startTime));
        $output->writeln(sprintf('<comment>Memory allocated %01.2f MB</comment>', memory_get_usage(true)/1048576));
        $output->writeln(sprintf('<comment>Memory allocated peak %01.2f MB</comment>', memory_get_peak_usage(true)/1048576));
    }


    protected function migrateSettings(
        InputInterface $input,
        OutputInterface $output,
        $idSite
    ) {
        $siteConfigMigrator = new SiteConfigMigrator($this->fromDbHelper, $this->toDbHelper, $this->idMapCollection);
        $output->writeln('<info>Migrating settings...</info>');
        $output->writeln('<comment> - Main settings</comment>');
        $siteConfigMigrator->migrateSiteConfig($idSite);

        $output->writeln('<comment> - Goals</comment>');
        $siteConfigMigrator->migrateSiteGoals($idSite);

        $output->writeln('<comment> - Site url</comment>');
        $siteConfigMigrator->migrateSiteURLs($idSite);

        unset($siteConfigMigrator);
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

    protected function migrateRawData(
        InputInterface $input,
        OutputInterface $output,
        $idSite
    ) {
        $actionMigrator      = new ActionMigrator($this->fromDbHelper, $this->toDbHelper, $this->idMapCollection);
        $visitMigrator       = new VisitMigrator($this->fromDbHelper, $this->toDbHelper, $this->idMapCollection, $actionMigrator);
        $visitActionMigrator = new LinkVisitActionMigrator($this->fromDbHelper, $this->toDbHelper, $this->idMapCollection, $actionMigrator);
        $conversionMigrator  = new ConversionMigrator($this->fromDbHelper, $this->toDbHelper, $this->idMapCollection);

        $output->writeln('<info>Migrating raw data... </info>');
        $output->writeln('<comment> - Action</comment>');
        $actionMigrator->migrateActions($idSite);
        $output->writeln('<comment> - Visit</comment>');
        $visitMigrator->migrateVisits($idSite);

        $output->writeln('<comment> - Link visit - action</comment>');
        $visitActionMigrator->migrateVisitActions($idSite);

        $output->writeln('<comment> - Conversion</comment>');
        $conversionMigrator->migrateConversions($idSite);

        $output->writeln('<comment> - Conversion item</comment>');
        $conversionMigrator->migrateConversionItems($idSite);
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


        if (!$input->getOption('host')) {
            $config['host'] = $this->askAndValidate(
                $output,
                'Please provide the destination database host',
                $notNullValidator,
                false,
                'localhost'
            );
        } else {
            $config['host'] = $input->getOption('host');
        }

        if (!$input->getOption('username')) {
            $config['username'] = $this->askAndValidate(
                $output,
                'Please provide the destination database username',
                $notNullValidator
            );
        } else {
            $config['username'] = $input->getOption('username');
        }

        if (!$input->getOption('password')) {
            $config['password'] = $this->askAndValidate(
                $output,
                'Please provide the destination database password',
                $dummyValidator
            );
        } else {
            $config['password'] = $input->getOption('password');
        }

        if (!$input->getOption('dbname')) {
            $config['dbname'] = $this->askAndValidate(
                $output,
                'Please provide the destination database name',
                $notNullValidator
            );
        } else {
            $config['dbname'] = $input->getOption('dbname');
        }

        $config['port']          = $input->getOption('port');
        $config['tables_prefix'] = $input->getOption('prefix');
    }

    protected function askAndValidate(
        OutputInterface $output,
        $question,
        callable $validator,
        $attempts = false,
        $default = null
    ) {
        /**
         * @var $dialog DialogHelper
         */
        $dialog = $this->getHelperSet()->get('dialog');

        return $dialog->askAndValidate(
            $output,
            '<question>' . $question . (($default) ? " [$default]" : '') . ':</question> ',
            $validator,
            $attempts,
            $default
        );
    }
}
