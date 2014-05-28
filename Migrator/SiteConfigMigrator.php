<?php


namespace Piwik\Plugins\SiteMigrator\Migrator;


use Piwik\Db;
use Piwik\Plugins\SiteMigrator\Helper\DBHelper;
use Piwik\Plugins\SiteMigrator\Model\IdMapCollection;

class SiteConfigMigrator
{

    protected $fromDbHelper;

    protected $toDbHelper;

    protected $idMapCollection;

    public function __construct(
        DBHelper $fromDb,
        DBHelper $toDb,
        IdMapCollection $idMapCollection
    ) {
        $this->fromDbHelper    = $fromDb;
        $this->toDbHelper      = $toDb;
        $this->idMapCollection = $idMapCollection;
    }

    /**
     * @param $idSite
     */
    public function migrateSiteConfig($idSite)
    {
        $this->toDbHelper->executeInsert('site', $this->getSiteConfig($idSite));
        $this->idMapCollection->getSiteMap()->add($idSite, $this->toDbHelper->lastInsertId());
    }

    /**
     * @param $idSite
     */
    public function migrateSiteGoals($idSite)
    {
        $goals = $this->getSiteGoals($idSite);
        $this->translateSiteId($goals);

        foreach ($goals as $goal) {
            $this->toDbHelper->executeInsert('goal', $goal);
        }
    }

    public function migrateSiteURLs($idSite)
    {
        $urls = $this->getSiteURLs($idSite);
        $this->translateSiteId($urls);

        foreach ($urls as $url) {
            $this->toDbHelper->executeInsert('site_url', $url);
        }
    }

    /**
     * @param $idSite
     * @return array goals
     */
    protected function getSiteGoals($idSite)
    {
        $goals = $this->fromDbHelper->getAdapter()->fetchAll(
            "SELECT * FROM " . $this->fromDbHelper->prefixTable("goal") . " WHERE idsite = ?",
            $idSite
        );

        return $goals;
    }

    /**
     * @param $idSite
     * @return array goals
     */
    protected function getSiteURLs($idSite)
    {
        $goals = $this->fromDbHelper->getAdapter()->fetchAll(
            "SELECT * FROM " . $this->fromDbHelper->prefixTable("site_url") . " WHERE idsite = ?",
            $idSite
        );

        return $goals;
    }

    /**
     * @param $data array data to change siteid in
     */
    protected function translateSiteId(&$data)
    {
        foreach ($data as $id => $val) {
            $data[$id]['idsite'] = $this->idMapCollection->getSiteMap()->translate($val['idsite']);
        }
    }

    /**
     * @param $idSite
     * @return array
     */
    protected function getSiteConfig($idSite)
    {
        $site = $this->fromDbHelper->getAdapter()->fetchRow(
            "SELECT * FROM " . $this->fromDbHelper->prefixTable("site") . " WHERE idsite = ?",
            $idSite
        );

        unset($site['idsite']);

        return $site;
    }

}