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

class LinkVisitActionMigrator
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

    public function migrateVisitActions($idSite)
    {
        $currentCount = 0;
        $loadAtOnce   = 10000;

        do {
            $visitActions = $this->getVisitsActionsQuery($idSite, $currentCount, $loadAtOnce);
            $count        = 0;

            while ($visitAction = $visitActions->fetch()) {
                try {
                    $this->processVisitAction($visitAction);
                } catch (\InvalidArgumentException $e) {
                    //skip this link visit action - lack of required data
                }
                $count++;
            }
            $currentCount += $count;
            $visitActions->closeCursor();

        } while($count >= $loadAtOnce);

        unset($visitActions);
        unset($visitAction);
        gc_collect_cycles();
    }

    protected function processVisitAction($visit)
    {
        $idVisitAction = $visit['idlink_va'];
        unset($visit['idlink_va']);

        $this->translateVisitActionData($visit);

        $this->toDbHelper->executeInsert('log_link_visit_action', $visit);
        $this->idMapCollection->getVisitActionMap()->add($idVisitAction, $this->toDbHelper->lastInsertId());
    }

    protected function translateVisitActionData(&$visitAction)
    {
        $visitAction['idsite']  = $this->idMapCollection->getSiteMap()->translate($visitAction['idsite']);
        $visitAction['idvisit'] = $this->idMapCollection->getVisitMap()->translate($visitAction['idvisit']);

        $visitAction['idaction_url']            = $this->translateAction($visitAction['idaction_url']);
        $visitAction['idaction_url_ref']        = $this->translateAction($visitAction['idaction_url_ref']);
        $visitAction['idaction_name']           = $this->translateAction($visitAction['idaction_name']);
        $visitAction['idaction_name_ref']       = $this->translateAction($visitAction['idaction_name_ref']);
        $visitAction['idaction_event_category'] = $this->translateAction($visitAction['idaction_event_category']);
        $visitAction['idaction_event_action']   = $this->translateAction($visitAction['idaction_event_action']);
    }

    protected function translateAction($idAction)
    {
        $this->actionMigrator->ensureActionIsMigrated($idAction);

        return $this->idMapCollection->getActionMap()->translate($idAction);
    }

    protected function getVisitsActionsQuery($idSite, $currentCount, $limit = 10000)
    {
        $query = $this->fromDbHelper->getAdapter()->prepare(
            'SELECT * FROM ' . $this->fromDbHelper->prefixTable(
                'log_link_visit_action'
            ) . ' WHERE idsite  = :idSite AND idvisit IN (' . implode(',', array_keys($this->idMapCollection->getVisitMap()->getIds())) . ')  LIMIT ' . $currentCount . ', ' . $limit
        );
        $query->execute(array('idSite' => $idSite));

        return $query;
    }


} 