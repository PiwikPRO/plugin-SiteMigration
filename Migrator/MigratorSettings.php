<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SiteMigration\Migrator;

class MigratorSettings
{
    public $skipArchiveData;

    public $skipLogData;

    public $idSite;

    public $site;

    public $dbHost;

    public $dbUsername;

    public $dbPassword;

    public $dbName;

    public $dbPrefix;

    public $dbPort;

    /**
     * @var \DateTime|null
     */
    public $dateFrom;

    /**
     * @var \DateTime|null
     */
    public $dateTo;

    public $newIdSite;
}
