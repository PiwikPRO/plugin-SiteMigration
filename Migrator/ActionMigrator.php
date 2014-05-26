<?php


namespace Piwik\Plugins\SiteMigrator\Migrator;

use Piwik\Plugins\SiteMigrator\Helper\DBHelper;
use Piwik\Plugins\SiteMigrator\Model\IdMapCollection;
use Symfony\Component\Config\Definition\Exception\Exception;

class ActionMigrator
{
    protected $fromDbHelper;

    protected $toDbHelper;

    protected $idMapCollection;

    protected $existingActions;

    public function __construct(
        DBHelper $fromDb,
        DBHelper $toDb,
        IdMapCollection $idMapCollection
    ) {
        $this->fromDbHelper = $fromDb;
        $this->toDbHelper = $toDb;
        $this->idMapCollection = $idMapCollection;
    }

    public function migrateActions($idSite)
    {
        $actions = $this->getActionsForIdSiteQuery($idSite);

        $this->loadExistingActions();

        while ($action = $actions->fetch()) {
            $this->processAction($action);
        }

        $actions->closeCursor();
        unset ($actions);
        gc_collect_cycles();

    }

    public function processAction($action)
    {
        if (array_key_exists($action['type'], $this->existingActions) && array_key_exists(
                $action['hash'],
                $this->existingActions[$action['type']]
            )
        ) {
            $this->idMapCollection->getActionMap()->add(
                $action['idaction'],
                $this->existingActions[$action['type']][$action['hash']]
            );
            return;
        }

        $idAction = $action['idaction'];
        unset($action['idaction']);
        $this->toDbHelper->executeInsert('log_action', $action);
        $this->idMapCollection->getActionMap()->add($idAction, $this->toDbHelper->lastInsertId());
        unset($action);
    }

    public function getActionIdsForIdSite($idSite)
    {
        $data = $this->fromDbHelper->getAdapter()->fetchCol(
            'SELECT DISTINCT idaction_url FROM ' . $this->fromDbHelper->prefixTable('log_link_visit_action')
            . ' WHERE idsite = :id_site AND idaction_url IS NOT NULL'
            . ' UNION DISTINCT SELECT idaction_url_ref FROM ' . $this->fromDbHelper->prefixTable(
                'log_link_visit_action'
            )
            . ' WHERE idsite = :id_site AND idaction_url_ref IS NOT NULL'
            . ' UNION DISTINCT SELECT idaction_name FROM ' . $this->fromDbHelper->prefixTable('log_link_visit_action')
            . ' WHERE idsite = :id_site AND idaction_name IS NOT NULL'
            . ' UNION DISTINCT SELECT idaction_name_ref FROM ' . $this->fromDbHelper->prefixTable(
                'log_link_visit_action'
            )
            . ' WHERE idsite = :id_site AND idaction_name_ref IS NOT NULL'
            . ' UNION DISTINCT SELECT idaction_event_category FROM ' . $this->fromDbHelper->prefixTable(
                'log_link_visit_action'
            )
            . ' WHERE idsite = :id_site AND idaction_event_category IS NOT NULL'
            . ' UNION DISTINCT SELECT idaction_event_action FROM ' . $this->fromDbHelper->prefixTable(
                'log_link_visit_action'
            )
            . ' WHERE idsite = :id_site AND idaction_event_action IS NOT NULL'
            . ' UNION DISTINCT SELECT visit_exit_idaction_url FROM ' . $this->fromDbHelper->prefixTable('log_visit')
            . ' WHERE idsite = :id_site AND visit_exit_idaction_url IS NOT NULL'
            . ' UNION DISTINCT SELECT visit_exit_idaction_name FROM ' . $this->fromDbHelper->prefixTable('log_visit')
            . ' WHERE idsite = :id_site AND visit_exit_idaction_name IS NOT NULL'
            . ' UNION DISTINCT SELECT visit_entry_idaction_url FROM ' . $this->fromDbHelper->prefixTable('log_visit')
            . ' WHERE idsite = :id_site AND visit_entry_idaction_url IS NOT NULL'
            . ' UNION DISTINCT SELECT visit_entry_idaction_name FROM ' . $this->fromDbHelper->prefixTable('log_visit')
            . ' WHERE idsite = :id_site AND visit_entry_idaction_name IS NOT NULL'
            . ' UNION DISTINCT SELECT idaction_url FROM ' . $this->fromDbHelper->prefixTable('log_conversion')
            . ' WHERE idsite = :id_site AND idaction_url IS NOT NULL'
            . ' UNION DISTINCT SELECT idaction_sku FROM ' . $this->fromDbHelper->prefixTable('log_conversion_item')
            . ' WHERE idsite = :id_site AND idaction_sku IS NOT NULL'
            . ' UNION DISTINCT SELECT idaction_name FROM ' . $this->fromDbHelper->prefixTable('log_conversion_item')
            . ' WHERE idsite = :id_site AND idaction_name IS NOT NULL'
            . ' UNION DISTINCT SELECT idaction_category FROM ' . $this->fromDbHelper->prefixTable('log_conversion_item')
            . ' WHERE idsite = :id_site AND idaction_category IS NOT NULL'
            . ' UNION DISTINCT SELECT idaction_category2 FROM ' . $this->fromDbHelper->prefixTable(
                'log_conversion_item'
            )
            . ' WHERE idsite = :id_site AND idaction_category2 IS NOT NULL'
            . ' UNION DISTINCT SELECT idaction_category3 FROM ' . $this->fromDbHelper->prefixTable(
                'log_conversion_item'
            )
            . ' WHERE idsite = :id_site AND idaction_category3 IS NOT NULL'
            . ' UNION DISTINCT SELECT idaction_category4 FROM ' . $this->fromDbHelper->prefixTable(
                'log_conversion_item'
            )
            . ' WHERE idsite = :id_site AND idaction_category4 IS NOT NULL'
            . ' UNION DISTINCT SELECT idaction_category5 FROM ' . $this->fromDbHelper->prefixTable(
                'log_conversion_item'
            )
            . ' WHERE idsite = :id_site AND idaction_category5 IS NOT NULL'
            ,
            array('id_site' => $idSite)
        );

        return $data;
    }

    public function getActionsForIdSiteQuery($idSite)
    {
        $query = $this->fromDbHelper->getAdapter()->prepare(
            'SELECT * FROM ' . $this->fromDbHelper->prefixTable('log_action') . ' WHERE idaction IN (' . implode(
                ', ',
                $this->getActionIdsForIdSite($idSite)
            ) . ')'
        );
        $query->execute();

        return $query;
    }

    protected function loadExistingActions()
    {
        $query = $this->toDbHelper->getAdapter()->prepare(
            'SELECT idaction, hash, type FROM ' . $this->toDbHelper->prefixTable('log_action')
        );
        $query->execute();

        $this->existingActions = array();

        while ($action = $query->fetch()) {
            $this->addExistingAction($action);
        }
    }

    protected function addExistingAction($action)
    {
        if (!array_key_exists($action['type'], $this->existingActions)) {
            $this->existingActions[$action['type']] = array();
        }

        $this->existingActions[$action['type']][$action['hash']] = $action['idaction'];
    }

    public function ensureActionIsMigrated($idAction)
    {

        try {
            $this->idMapCollection->getActionMap()->translate($idAction);

            return true;
        } catch (\InvalidArgumentException $e) {
            $action = $this->fromDbHelper->getAdapter()->fetchRow(
                'SELECT * FROM ' . $this->fromDbHelper->prefixTable('log_action') . ' WHERE idaction = ?',
                array($idAction)
            );

            if ($action) {
                $this->processAction($action);

                return true;
            }
        }

        return false;
    }
} 