<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SiteMigration\Migrator\Archive;
use Piwik\Plugins\SiteMigration\Helper\DBHelper;

/**
 * Lists archive tables.
 */
class ArchiveLister
{
    /**
     * @var DBHelper
     */
    private $database;

    public function __construct(DBHelper $database)
    {
        $this->database = $database;
    }

    /**
     * Returns the archive list as strings looking like this: '2014-01'
     *
     * @param \DateTime $from
     * @param \DateTime $to
     *
     * @return string[]
     */
    public function getArchiveList(\DateTime $from = null, \DateTime $to = null)
    {
        $tablePrefix = $this->database->prefixTable('archive_numeric_');

        // We can't use Piwik\DataAccess\ArchiveTableCreator::getTablesArchivesInstalled()
        // because of the global DB object: it will use the "sourceDb" instead of the "targetDb"...
        // TODO Fix later when we have dependency injection
        $archives = $this->database->getAdapter()->fetchCol("SHOW TABLES LIKE '" . $tablePrefix . "%'");

        $archives = array_map(function ($value) use ($tablePrefix) {
            return str_replace($tablePrefix, '', $value);
        }, $archives);

        $archives = array_filter($archives, function ($archive) use ($from, $to) {
            $date = new \DateTime(str_replace('_', '-', $archive) . '-01');

            $excluded = ($from && $from > $date) || ($to && $to < $date);

            return !$excluded;
        });

        return array_values($archives);
    }
}
