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
        $this->existingActions = array();
    }

    protected function processAction($action)
    {
        if (array_key_exists($action['type'], $this->existingActions)
            && array_key_exists($action['hash'],$this->existingActions[$action['type']])
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

    public function loadExistingActions()
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