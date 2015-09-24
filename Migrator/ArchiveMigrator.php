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
use Piwik\Plugins\SiteMigration\Helper\GCHelper;
use Piwik\Plugins\SiteMigration\Migrator\Archive\ArchiveLister;
use Piwik\Plugins\SiteMigration\Model\SiteDefinition;
use Piwik\Sequence;

class ArchiveMigrator extends BaseMigrator
{
    /**
     * @var SiteMigrator
     */
    private $siteMigrator;

    /**
     * @var ArchiveLister
     */
    private $archiveLister;

    public function __construct(
        SiteDefinition $sourceDef,
        SiteDefinition $targetDef,
        GCHelper $gcHelper,
        SiteMigrator $siteMigrator,
        ArchiveLister $archiveLister
    )
    {
        $this->siteMigrator = $siteMigrator;
        $this->archiveLister = $archiveLister;

        parent::__construct($sourceDef, $targetDef, $gcHelper);
    }

    public function migrate($siteId, \DateTime $from = null, \DateTime $to = null)
    {
        $archives = $this->archiveLister->getArchiveList($from, $to);

        foreach ($archives as $archiveDate) {
            Log::debug('Migrating archive ' . $archiveDate);

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

        $this->targetDef->getDbHelper()->executeInsert($archiveTable, $record);
    }

    private function ensureTargetTableExists($archiveTable)
    {
        $targetDbHelper = $this->targetDef->getDbHelper();
        $data = $targetDbHelper->getAdapter()->fetchCol(
            "SHOW TABLES LIKE '" . $targetDbHelper->prefixTable($archiveTable) . "'"
        );

        if (count($data) == 0) {
            $tableType = (strpos($archiveTable, 'blob')) ? 'archive_blob' : 'archive_numeric';
            $sql       = PiwikDbHelper::getTableCreateSql($tableType);
            $sql       = str_replace($tableType, $archiveTable, $sql);
            $sql       = str_replace($this->sourceDef->getDbHelper()->prefixTable($tableType), $targetDbHelper->prefixTable($tableType), $sql);

            $targetDbHelper->getAdapter()->query($sql);
        }
    }

    private function getArchiveRecordsQuery($archiveTable, $idSite)
    {
        $sourceDbHelper = $this->sourceDef->getDbHelper();
        $query = $sourceDbHelper->getAdapter()->prepare(
            'SELECT * FROM ' . $sourceDbHelper->prefixTable($archiveTable) . ' WHERE idsite = ?'
        );
        $query->execute(array($idSite));

        return $query;
    }

    private function getArchiveId($archiveDate, $archiveId)
    {
        if (! isset($this->idMap[$archiveDate][$archiveId])) {

            $targetDbHelper = $this->targetDef->getDbHelper();

            $sequence = new Sequence(
                $targetDbHelper->prefixTable('archive_numeric_' . $archiveDate),
                $targetDbHelper->getAdapter(),
                $targetDbHelper->prefixTable('')
            );

            if (! $sequence->exists()) {
                $sequence->create();
            }

            $this->idMap[$archiveDate][$archiveId] = $sequence->getNextId();
        }

        return $this->idMap[$archiveDate][$archiveId];
    }
}
