<?php


namespace Piwik\Plugins\SiteMigrator\Model;


class IdMapCollection
{

    /**
     * @var IdMap $siteMap
     */
    protected $siteMap;

    /**
     * @var IdMap $siteMap
     */
    protected $actionMap;

    /**
     * @var IdMap $visitMap
     */
    protected $visitMap;

    /**
     * @var IdMap $visitActionMap
     */
    protected $visitActionMap;


    /**
     * Constructor
     */
    function __construct()
    {
        $this->siteMap        = new IdMap();
        $this->actionMap      = new IdMap();
        $this->visitMap       = new IdMap();
        $this->visitActionMap = new IdMap();

        $this->actionMap->add(0, 0);
    }

    /**
     * @param IdMap $siteMap
     */
    public function setSiteMap(IdMap $siteMap)
    {
        $this->siteMap = $siteMap;
    }

    /**
     * @return IdMap
     */
    public function getSiteMap()
    {
        return $this->siteMap;
    }

    /**
     * @param IdMap $actionMap
     */
    public function setActionMap($actionMap)
    {
        $this->actionMap = $actionMap;
    }

    /**
     * @return IdMap
     */
    public function getActionMap()
    {
        return $this->actionMap;
    }

    /**
     * @param \Piwik\Plugins\SiteMigrator\Model\IdMap $visitMap
     */
    public function setVisitMap($visitMap)
    {
        $this->visitMap = $visitMap;
    }

    /**
     * @return \Piwik\Plugins\SiteMigrator\Model\IdMap
     */
    public function getVisitMap()
    {
        return $this->visitMap;
    }

    /**
     * @param \Piwik\Plugins\SiteMigrator\Model\IdMap $visitActionMap
     */
    public function setVisitActionMap($visitActionMap)
    {
        $this->visitActionMap = $visitActionMap;
    }

    /**
     * @return \Piwik\Plugins\SiteMigrator\Model\IdMap
     */
    public function getVisitActionMap()
    {
        return $this->visitActionMap;
    }

} 