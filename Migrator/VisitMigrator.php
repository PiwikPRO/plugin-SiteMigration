<?php


namespace Piwik\Plugins\SiteMigrator\Migrator;

use Piwik\Plugins\SiteMigrator\Helper\DBHelper;
use Piwik\Plugins\SiteMigrator\Model\IdMapCollection;

class VisitMigrator
{
    protected $fromDbHelper;

    protected $toDbHelper;

    protected $idMapCollection;

    protected $actionMigrator;

    public function __construct(
        DBHelper $fromDb,
        DBHelper $toDb,
        IdMapCollection $idMapCollection,
        ActionMigrator $actionMigrator
    ) {
        $this->fromDbHelper    = $fromDb;
        $this->toDbHelper      = $toDb;
        $this->idMapCollection = $idMapCollection;
        $this->actionMigrator  = $actionMigrator;
    }

    public function migrateVisits($idSite)
    {
        $currentCount = 0;

        do {
            $visits = $this->getVisitsQuery($idSite, $currentCount);
            $count  = 0;

            while ($visit = $visits->fetch()) {
                try {
                    $this->processVisit($visit);
                } catch (\InvalidArgumentException $e) {
                    //action nto found, skip
                }
                $count++;
            }
            $currentCount += $count;

            $visits->closeCursor();
        } while ($count > 0);

        unset($visits);
        unset($count);
        unset($currentCount);
        unset($visit);
        gc_collect_cycles();
    }

    protected function processVisit($visit)
    {
        $idVisit = $visit['idvisit'];
        unset($visit['idvisit']);

        $this->translateVisitData($visit);

        $this->toDbHelper->executeInsert('log_visit', $visit);
        $this->idMapCollection->getVisitMap()->add($idVisit, $this->toDbHelper->lastInsertId());
    }

    protected function translateVisitData(&$visit)
    {
        $visit['idsite'] = $this->idMapCollection->getSiteMap()->translate($visit['idsite']);

        $visit['visit_exit_idaction_url']   = $this->translateAction($visit['visit_exit_idaction_url']);
        $visit['visit_exit_idaction_name']  = $this->translateAction($visit['visit_exit_idaction_name']);
        $visit['visit_entry_idaction_url']  = $this->translateAction($visit['visit_entry_idaction_url']);
        $visit['visit_entry_idaction_name'] = $this->translateAction($visit['visit_entry_idaction_name']);
    }

    protected function translateAction($idAction)
    {
        $this->actionMigrator->ensureActionIsMigrated($idAction);

        return $this->idMapCollection->getActionMap()->translate($idAction);
    }

    protected function getVisitsQuery($idSite, $currentCount, $limit = 10000, $criteria = array())
    {
        $parameters = array('idSite' => $idSite);
        $andWhere   = '';

        if (count($criteria) > 0) {
            $i = 0;
            foreach($criteria as $key => $val) {
                $andWhere .= 'AND ' . $key . ((isset($val['operator']))? $val['operator']: '=') . ':param' . $i;
                $i++;
                $parameters['param' . $i] = $val;
            }
        }

        $sql   = 'SELECT * FROM ' . $this->fromDbHelper->prefixTable(
                'log_visit'
            ) . ' WHERE idsite = :idSite ' . $andWhere . ' LIMIT ' . $currentCount . ', ' . $limit;
        $query = $this->fromDbHelper->getAdapter()->prepare($sql);

        $query->execute($parameters);

        return $query;
    }


} 