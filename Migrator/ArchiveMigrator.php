<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SiteMigration\Migrator;

use Piwik\Archive;
use Piwik\Db\Schema;
use Piwik\DbHelper as PiwikDbHelper;
use Piwik\Log;
use Piwik\Plugins\SiteMigration\Helper\DBHelper;
use Piwik\Plugins\SiteMigration\Migrator\Archive\ArchiveLister;
use Piwik\Sequence;

class ArchiveMigrator
{
    /**
     * @var DBHelper
     */
    private $sourceDb;

    /**
     * @var DBHelper
     */
    private $targetDb;

    /**
     * @var SiteMigrator
     */
    private $siteMigrator;

    /**
     * @var ArchiveLister
     */
    private $archiveLister;

    /**
     * Map of old archive IDs (source DB) to new archive IDs (target DB).
     *
     * @var int[]
     */
    private $archiveIdMap = array();

    public function __construct(DBHelper $sourceDb, DBHelper $targetDb, SiteMigrator $siteMigrator, ArchiveLister $archiveLister)
    {
        $this->sourceDb = $sourceDb;
        $this->targetDb = $targetDb;
        $this->siteMigrator = $siteMigrator;
        $this->archiveLister = $archiveLister;
    }

    public function migrate($siteId, \DateTime $from = null, \DateTime $to = null)
    {
        $archives = $this->archiveLister->getArchiveList($from, $to);

        foreach ($archives as $archiveDate) {
            Log::info('Migrating archive ' . $archiveDate);

            $this->migrateArchive($archiveDate, 'archive_numeric_' . $archiveDate, $siteId);

            try {
                $this->migrateArchive($archiveDate, 'archive_blob_' . $archiveDate, $siteId);
            } catch(\Exception $e) {
                // blob tables can be missing
            }
        }
    }

    private function migrateArchive($archiveDate, $archiveTable, $siteId)
    {
        $this->ensureTargetTableExists($archiveTable);

        $records = $this->getArchiveRecordsQuery($archiveTable, $siteId);

        while ($record = $records->fetch()) {
            $this->processArchive($archiveDate, $archiveTable, $record);
        }
    }

    private function processArchive($archiveDate, $archiveTable, $record)
    {
        $record['idarchive'] = $this->getArchiveId($archiveDate, $record['idarchive']);
        $record['idsite']    = $this->siteMigrator->getNewId($record['idsite']);

        $this->targetDb->executeInsert($archiveTable, $record);
    }

    private function ensureTargetTableExists($archiveTable)
    {
        $data = $this->targetDb->getAdapter()->fetchCol(
            "SHOW TABLES LIKE '" . $this->targetDb->prefixTable($archiveTable) . "'"
        );

        if (count($data) == 0) {
            $tableType = (strpos($archiveTable, 'blob')) ? 'archive_blob' : 'archive_numeric';
            $sql       = PiwikDbHelper::getTableCreateSql($tableType);
            $sql       = str_replace($tableType, $archiveTable, $sql);
            $sql       = str_replace($this->sourceDb->prefixTable(''), $this->targetDb->prefixTable(''), $sql);

            $this->targetDb->getAdapter()->query($sql);
        }
    }

    private function getArchiveRecordsQuery($archiveTable, $idSite)
    {
        $query = $this->sourceDb->getAdapter()->prepare(
            'SELECT * FROM ' . $this->sourceDb->prefixTable($archiveTable) . ' WHERE idsite = ?'
        );
        $query->execute(array($idSite));

        return $query;
    }

    private function getArchiveId($archiveDate, $archiveId)
    {
        if (! isset($this->archiveIdMap[$archiveDate][$archiveId])) {

            $sequence = new Sequence(
                $this->targetDb->prefixTable('archive_numeric_' . $archiveDate),
                $this->targetDb
            );

            if (! $sequence->exists()) {
                $sequence->create();
            }

            $this->archiveIdMap[$archiveDate][$archiveId] = $sequence->getNextId();
        }

        return $this->archiveIdMap[$archiveDate][$archiveId];
    }
}
