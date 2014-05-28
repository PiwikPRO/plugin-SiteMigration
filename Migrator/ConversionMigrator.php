<?php


namespace Piwik\Plugins\SiteMigrator\Migrator;

use Piwik\Plugins\SiteMigrator\Helper\DBHelper;
use Piwik\Plugins\SiteMigrator\Model\IdMapCollection;
use \InvalidArgumentException;

class ConversionMigrator
{
    protected $fromDbHelper;

    protected $toDbHelper;

    protected $idMapCollection;

    protected $actionMigrator;

    protected $actionsToTranslate = array(
        'idaction_sku',
        'idaction_name',
        'idaction_category',
        'idaction_category2',
        'idaction_category3',
        'idaction_category4',
        'idaction_category5'
    );

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

    public function migrateConversions($idSite)
    {
        $conversions = $this->getConversionsQuery($idSite);

        while ($conversion = $conversions->fetch()) {
            try {
                $this->processConversion($conversion);
            } catch (InvalidArgumentException $e ) {
                // do nothing, just skip
            }

        }
        $conversions->closeCursor();
    }

    public function migrateConversionItems($idSite)
    {
        $items = $this->getConversionItemsQuery($idSite);

        while ($item = $items->fetch()) {
            try {
                $this->processConversionItem($item);
            } catch (InvalidArgumentException $e ) {
                // do nothing, just skip
            }

        }
        $items->closeCursor();

        unset($items);
        gc_collect_cycles();
    }

    protected function processConversion($conversion)
    {
        $this->translateConversionData($conversion);
        $this->toDbHelper->executeInsert('log_conversion', $conversion);
    }

    protected function processConversionItem($item)
    {
        $this->translateConversionItemData($item);
        $this->toDbHelper->executeInsert('log_conversion_item', $item);
    }

    protected function translateConversionData(&$conversion)
    {

        $conversion['idsite']  = $this->idMapCollection->getSiteMap()->translate($conversion['idsite']);
        $conversion['idvisit'] = $this->idMapCollection->getVisitMap()->translate($conversion['idvisit']);

        if ($conversion['idlink_va']) {
            $conversion['idlink_va'] = $this->idMapCollection->getVisitActionMap()->translate($conversion['idlink_va']);
        }

        if ($conversion['idaction_url']) {
            $conversion['idaction_url'] = $this->translateAction(
                $conversion['idaction_url']
            );
        }

    }

    protected function translateConversionItemData(&$item)
    {

        $item['idsite']  = $this->idMapCollection->getSiteMap()->translate($item['idsite']);
        $item['idvisit'] = $this->idMapCollection->getVisitMap()->translate($item['idvisit']);

        foreach ($this->actionsToTranslate as $translationKey) {
            if ($item[$translationKey] == 0) {
                continue;
            }

            $item[$translationKey] = $this->translateAction($item[$translationKey]);
        }

    }

    protected function getConversionsQuery($idSite)
    {
        $query = $this->fromDbHelper->getAdapter()->prepare(
            'SELECT * FROM ' . $this->fromDbHelper->prefixTable('log_conversion') . ' WHERE idsite  = :idSite AND idvisit IN (' . implode(',', array_keys($this->idMapCollection->getVisitMap()->getIds())) . ')'
        );
        $query->execute(array('idSite' => $idSite));

        return $query;
    }

    protected function translateAction($idAction)
    {
        $this->actionMigrator->ensureActionIsMigrated($idAction);

        return $this->idMapCollection->getActionMap()->translate($idAction);
    }

    protected function getConversionItemsQuery($idSite)
    {
        $query = $this->fromDbHelper->getAdapter()->prepare(
            'SELECT * FROM ' . $this->fromDbHelper->prefixTable('log_conversion_item') . ' WHERE idsite  = :idSite AND idvisit IN (' . implode(',', array_keys($this->idMapCollection->getVisitMap()->getIds())) . ')'
        );
        $query->execute(array('idSite' => $idSite));

        return $query;
    }


} 