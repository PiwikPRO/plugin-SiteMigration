<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */


namespace Piwik\Plugins\SiteMigration\Migrator;

use Piwik\Archive;
use Piwik\Db\Schema;
use Piwik\DbHelper as PiwikDbHelper;
use Piwik\Plugins\SiteMigration\Helper\DBHelper;

class ArchiveMigrator
{
    protected $fromDbHelper;

    protected $toDbHelper;

    /**
     * @var SiteMigrator
     */
    protected $siteMigrator;

    public function __construct(
        DBHelper $fromDb,
        DBHelper $toDb,
        SiteMigrator $siteMigrator
    )
    {
        $this->fromDbHelper = $fromDb;
        $this->toDbHelper   = $toDb;
        $this->siteMigrator = $siteMigrator;
    }

    public function getArchiveList($dateFrom = null, $dateTo = null)
    {
        $tables = $this->fromDbHelper->getAdapter()->fetchCol("SHOW TABLES LIKE '" . $this->fromDbHelper->prefixTable('archive_') . "%'");
        $prefix = $this->fromDbHelper->prefixTable('');

        array_walk(
            $tables,
            function (&$value, $key) use ($prefix) {
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

    protected function processArchive($archive, $record)
    {
        $lockName = 'allocateNewarchiveId.' . $archive;

        $this->toDbHelper->acquireLock($lockName);

        $id = $this->getNextArchiveId($archive, $record['name']);

        $record['idarchive'] = $id;
        $record['idsite']    = $this->siteMigrator->getNewId($record['idsite']);

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