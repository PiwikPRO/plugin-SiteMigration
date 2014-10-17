<?php


namespace Piwik\Plugins\SiteMigration\Migrator;


class SiteMigrator extends Migrator
{

    protected function getTableName()
    {
        return 'site';
    }

    protected function getIdFromRow(&$row)
    {
        return $row['idsite'];
    }

    protected function translateRow(&$row)
    {
        unset ($row['idsite']);
    }
}