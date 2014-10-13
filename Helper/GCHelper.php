<?php


namespace Piwik\Plugins\SiteMigration\Helper;


use Piwik\Singleton;

class GCHelper extends Singleton
{

    protected function __construct()
    {
        $this->enableGC();

        parent::__construct();
    }

    public function enableGC()
    {
        if (!gc_enabled()) {
            gc_enable();
        }
    }

    public function cleanup()
    {
        gc_collect_cycles();
    }

    public function cleanVariable(&$variable)
    {
        unset($variable);
    }

} 