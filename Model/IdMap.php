<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */


namespace Piwik\Plugins\SiteMigration\Model;


use Piwik\Plugins\SiteMigration\Exception\MissingIDTranslationException;

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
     * @throws MissingIDTranslationException If no match found
     */
    public function translate($oldId)
    {
        $oldId = (int)$oldId;
        if (isset($this->ids[$oldId])) {
            return $this->ids[$oldId];
        }

        throw new MissingIDTranslationException('Value for key ' . $oldId . ' not found');
    }


} 