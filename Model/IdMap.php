<?php


namespace Piwik\Plugins\SiteMigrator\Model;


class IdMap
{

    protected $ids = array();

    /**
     * @return int[]
     */
    public function getIds()
    {
        return $this->ids;
    }

    public function add($oldId, $newId)
    {
        $this->ids[$oldId] = $newId;
    }

    /**
     * @param $oldId
     * @return mixed
     * @throws \InvalidArgumentException If no match found
     */
    public function translate($oldId)
    {
        $oldId = (int)$oldId;
        if (isset($this->ids[$oldId])) {
            return $this->ids[$oldId];
        }

        throw new \InvalidArgumentException('Value for key ' . $oldId . ' not found');
    }


} 