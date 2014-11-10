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
use Piwik\Plugins\SiteMigration\Helper\DBHelper;

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

    public function __construct(
        DBHelper $sourceDb,
        DBHelper $targetDb,
        SiteMigrator $siteMigrator
    ) {
        $this->sourceDb = $sourceDb;
        $this->targetDb = $targetDb;
        $this->siteMigrator = $siteMigrator;
    }

    public function getArchiveList(\DateTime $dateFrom = null, \DateTime $dateTo = null)
    {
        $tables = $this->sourceDb->getAdapter()->fetchCol("SHOW TABLES LIKE '" . $this->sourceDb->prefixTable('archive_') . "%'");
        $prefix = $this->sourceDb->prefixTable('');

        array_walk(
            $tables,
            function (&$value) use ($prefix) {
                $value = str_replace($prefix, '', $value);
            }
        );

        if ($dateFrom || $dateTo) {
            foreach ($tables as $key => $table) {
                $date = str_replace('_', '-', substr($table, -7)) . '-01';
                $date = new \DateTime($date);
                if (($dateFrom && $dateFrom > $date) || ($dateTo && $dateTo < $date)) {
                    unset($tables[$key]);
                }
            }
        }

        return $tables;
    }

    public function migrateArchive($archive, $idSite)
    {
        $this->ensureTargetTableExists($archive);

        $records = $this->getArchiveRecordsQuery($archive, $idSite);

        while ($record = $records->fetch()) {
            $this->processArchive($archive, $record);
        }
    }

    private function processArchive($archive, $record)
    {
        $lockName = 'allocateNewarchiveId.' . $archive;

        $this->targetDb->acquireLock($lockName);

        $id = $this->getNextArchiveId($archive, $record['name']);

        $record['idarchive'] = $id;
        $record['idsite']    = $this->siteMigrator->getNewId($record['idsite']);

        $this->targetDb->executeInsert($archive, $record);

        $this->targetDb->releaseLock($lockName);
    }

    private function ensureTargetTableExists($archive)
    {
        $data = $this->targetDb->getAdapter()->fetchCol(
            "SHOW TABLES LIKE '" . $this->targetDb->prefixTable($archive) . "'"
        );

        if (count($data) == 0) {
            $tableType = (strpos($archive, 'blob')) ? 'archive_blob' : 'archive_numeric';
            $sql       = PiwikDbHelper::getTableCreateSql($tableType);
            $sql       = str_replace($tableType, $archive, $sql);
            $sql       = str_replace($this->sourceDb->prefixTable(''), $this->targetDb->prefixTable(''), $sql);

            $this->targetDb->getAdapter()->query($sql);
        }
    }

    private function getArchiveRecordsQuery($archive, $idSite)
    {
        $query = $this->sourceDb->getAdapter()->prepare(
            'SELECT * FROM ' . $this->sourceDb->prefixTable($archive) . ' WHERE idsite = ?'
        );
        $query->execute(array($idSite));

        return $query;
    }

    private function getNextArchiveId($archive, $name)
    {
        $data = $this->targetDb->getAdapter()->fetchCol(
            'SELECT IFNULL(MAX(idarchive), 0) + 1 FROM ' . $this->targetDb->prefixTable(
                $archive
            ) . ' WHERE name = :name',
            array('name' => $name)
        );

        return $data[0];
    }
}
