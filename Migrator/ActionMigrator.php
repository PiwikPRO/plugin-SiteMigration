<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SiteMigration\Migrator;

use Piwik\Plugins\SiteMigration\Helper\DBHelper;

class ActionMigrator
{
    protected $sourceDb;

    protected $targetDb;

    protected $existingActions = array();

    protected $idMap = array();

    public function __construct(DBHelper $sourceDb, DBHelper $targetDb)
    {
        $this->sourceDb = $sourceDb;
        $this->targetDb = $targetDb;
    }

    protected function processAction($action)
    {
        if (array_key_exists($action['type'], $this->existingActions)
            && array_key_exists($action['hash'], $this->existingActions[$action['type']])
        ) {
            $this->idMap[$action['idaction']] = $this->existingActions[$action['type']][$action['hash']];

            return;
        }

        $idAction = $action['idaction'];
        unset($action['idaction']);
        $this->targetDb->executeInsert('log_action', $action);
        $this->idMap[$idAction] = $this->targetDb->lastInsertId();
        unset($action);
    }

    public function loadExistingActions()
    {
        $query = $this->targetDb->getAdapter()->prepare(
            'SELECT idaction, hash, type FROM ' . $this->targetDb->prefixTable('log_action')
        );
        $query->execute();

        $this->existingActions = array();

        while ($action = $query->fetch()) {
            $this->addExistingAction($action);
        }
    }

    public function addExistingAction($action)
    {
        if (!array_key_exists($action['type'], $this->existingActions)) {
            $this->existingActions[$action['type']] = array();
        }

        $this->existingActions[$action['type']][$action['hash']] = $action['idaction'];
    }

    public function ensureActionIsMigrated($idAction)
    {
        if (array_key_exists($idAction, $this->idMap)) {
            return true;
        } else {
            $action = $this->sourceDb->getAdapter()->fetchRow(
                'SELECT * FROM ' . $this->sourceDb->prefixTable('log_action') . ' WHERE idaction = ?',
                array($idAction)
            );

            if ($action) {
                $this->processAction($action);

                return true;
            }
        }

        return false;
    }

    public function getNewId($idAction)
    {
        if ($this->ensureActionIsMigrated($idAction)) {
            return $this->idMap[$idAction];
        } else {
            return 0;
        }

    }

    /**
     * @param array $existingActions
     */
    public function setExistingActions($existingActions)
    {
        $this->existingActions = $existingActions;
    }

    /**
     * @return array
     */
    public function getExistingActions()
    {
        return $this->existingActions;
    }

    /**
     * @param int $oldId
     * @param int $newId
     */
    public function addNewId($oldId, $newId)
    {
        $this->idMap[$oldId] = $newId;
    }
}
