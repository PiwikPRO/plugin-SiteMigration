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

use Piwik\Plugins\SiteMigration\Helper\DBHelper;
use Piwik\Plugins\SiteMigration\Model\IdMapCollection;
use Symfony\Component\Config\Definition\Exception\Exception;

class VisitMigrator
{
    protected $fromDbHelper;

    protected $toDbHelper;

    protected $idMapCollection;

    protected $actionMigrator;

    protected $where;

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
        $this->where           = array();
    }

    public function migrateVisits($idSite)
    {
        $currentCount = 0;
        $limit        = 10000;

        do {
            $visits = $this->getVisitsQuery($idSite, $currentCount, $limit);
            $count  = 0;

            while ($visit = $visits->fetch()) {
                try {
                    $this->processVisit($visit);
                } catch (\InvalidArgumentException $e) {
                }
                $count++;
            }
            $currentCount += $count;

            $visits->closeCursor();
        } while ($count >= $limit);

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

    public function getVisitsQuery($idSite, $currentCount = 0, $limit = 10000)
    {
        return $this->prepareQuery($idSite, '*', $currentCount, $limit);
    }

    /**
     * @param $idSite
     * @param string $select
     * @param int $currentCount
     * @param null $limit
     * @return \PDOStatement|\Zend_Db_Statement
     */
    protected function prepareQuery($idSite, $select = '*', $currentCount = 0, $limit = null)
    {
        $parameters = array('idSite' => $idSite);
        $andWhere   = '';
        $where      = $this->getWhere();

        if (count($where) > 0) {
            $i = 0;
            foreach ($where as $val) {
                $andWhere .= ' AND `' . $val['column'] . '` ' . $val['operator'] . ' :param' . $i;
                $parameters['param' . $i] = $val['value'];
                $i++;
            }
        }

        $sql = 'SELECT ' . $select . ' FROM ' . $this->fromDbHelper->prefixTable(
                'log_visit'
            ) . ' WHERE idsite = :idSite' . $andWhere;

        if ($limit) {
            $sql .= ' LIMIT ' . $currentCount . ', ' . $limit;
        }

        $query = $this->fromDbHelper->getAdapter()->prepare($sql);
        $query->execute($parameters);

        return $query;
    }

    public function getVisitCount($idSite)
    {
        return $this->prepareQuery($idSite, 'COUNT(idvisit)')->fetchColumn(0);
    }

    public function andWhere($column, $value, $operator = '=')
    {
        $this->where[] = array('column' => $column, 'value' => $value, 'operator' => $operator);

        return $this;
    }

    /**
     * @return array
     */
    public function getWhere()
    {
        return $this->where;
    }


} 