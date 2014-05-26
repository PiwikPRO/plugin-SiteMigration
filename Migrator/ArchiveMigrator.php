<?php


namespace Piwik\Plugins\SiteMigrator\Migrator;

use Piwik\Db\Schema;
use Piwik\Plugins\SiteMigrator\Helper\DBHelper;
use Piwik\Plugins\SiteMigrator\Model\IdMapCollection;
use Piwik\DbHelper as PiwikDbHelper;

class ArchiveMigrator
{
    protected $fromDbHelper;

    protected $toDbHelper;

    protected $idMapCollection;

    public function __construct(
        DBHelper $fromDb,
        DBHelper $toDb,
        IdMapCollection $idMapCollection
    ) {
        $this->fromDbHelper = $fromDb;
        $this->toDbHelper = $toDb;
        $this->idMapCollection = $idMapCollection;
    }

    public function getArchiveList()
    {
        $tables = $this->fromDbHelper->getAdapter()->fetchCol("SHOW TABLES LIKE 'piwik_archive_%'");
        $prefix = $this->fromDbHelper->prefixTable('');

        array_walk(
            $tables,
            function (&$value, $key) use ($prefix) {
                $value = str_replace($prefix, '', $value);
            }
        );

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

    protected function processArchive($archive, $record)
    {
        $lockName = 'allocateNewarchiveId.' . $archive;

        $this->toDbHelper->acquireLock($lockName);

        $id = $this->getNextArchiveId($archive, $record['name']);

        $record['idarchive'] = $id;
        $record['idsite']    = $this->idMapCollection->getSiteMap()->translate($record['idsite']);

        $this->toDbHelper->executeInsert($archive, $record);

        $this->toDbHelper->releaseLock($lockName);
    }


    protected function ensureTargetTableExists($archive)
    {
        $data = $this->toDbHelper->getAdapter()->fetchCol(
            "SHOW TABLES LIKE '" . $this->toDbHelper->prefixTable($archive) . "'"
        );

        if (count($data) == 0) {
            $tableType = (strpos($archive, 'blob')) ? 'archive_blob' : 'archive_numeric';
            $sql       = PiwikDbHelper::getTableCreateSql($tableType);
            $sql       = str_replace($tableType, $archive, $sql);
            $sql       = str_replace($this->fromDbHelper->prefixTable(''), $this->toDbHelper->prefixTable(''), $sql);

            $this->toDbHelper->getAdapter()->query($sql);
        }
    }

    protected function getArchiveRecordsQuery($archive, $idSite)
    {
        $query = $this->fromDbHelper->getAdapter()->prepare(
            'SELECT * FROM ' . $this->fromDbHelper->prefixTable($archive) . ' WHERE idsite = ?'
        );
        $query->execute(array($idSite));

        return $query;
    }

    protected function getNextArchiveId($archive, $name)
    {
        $data = $this->toDbHelper->getAdapter()->fetchCol(
            'SELECT IFNULL(MAX(idarchive), 0) + 1 FROM ' . $this->toDbHelper->prefixTable(
                $archive
            ) . ' WHERE name = :name',
            array('name' => $name)
        );

        return $data[0];
    }
} 