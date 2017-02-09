<?php
/**
 * Piwik PRO -  Premium functionality and enterprise-level support for Piwik Analytics
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SiteMigration\DataProvider;

use Piwik\Plugins\SiteMigration\Helper\DBHelper;
use Piwik\Plugins\SiteMigration\Helper\GCHelper;

class BatchProvider implements \Iterator
{
    /**
     * @var DBHelper
     */
    protected $fromDbHelper;

    protected $loadAtOnce;

    protected $start = 0;

    /**
     * @var \PDOStatement
     */
    protected $rows;

    protected $row = null;

    protected $currentPosition = 0;

    protected $query;

    protected $queries;

    protected $queriesCanonical;

    protected $gcHelper;

    public function __construct($queries, $fromDbHelper, GCHelper $gcHelper, $loadAtOnce = 10000)
    {
        if (!is_array($queries)) {
            $queries = array($queries);
        }

        $this->queries          = $queries;
        $this->queriesCanonical = $queries;
        $this->fromDbHelper     = $fromDbHelper;
        $this->loadAtOnce       = $loadAtOnce;
        $this->gcHelper         = $gcHelper;
        $this->query            = array_shift($this->queries);
    }

    public function current()
    {
        return $this->row;
    }

    public function next()
    {
        $this->loadNextRow();
    }

    public function key()
    {
        return $this->start + $this->currentPosition;
    }

    public function valid()
    {
        return ($this->row != null);
    }

    public function rewind()
    {
        $this->start   = 0;
        $this->row     = null;
        $this->rows    = null;
        $this->queries = $this->queriesCanonical;
        $this->query   = array_shift($this->queries);

        $this->initQuery();
        $this->loadNextRow();
    }

    protected function initQuery()
    {
        if ($this->rows instanceof \PDOStatement) {
            $this->cleanupResults();
        }

        $this->rows = $this->fromDbHelper->getAdapter()->prepare(
            $this->query . ' LIMIT ' . $this->start . ', ' . $this->loadAtOnce
        );

        $this->rows->execute();

        $this->currentPosition = 0;
    }

    protected function loadNextRow()
    {
        if (!$this->rows) {
            return;
        }

        if ($this->currentPosition == $this->loadAtOnce) {
            $this->start += $this->loadAtOnce;
            $this->initQuery();
        }

        $this->row = null;

        while (!$this->row && $this->query) {
            $this->row = $this->rows->fetch();
            $this->ensureNextRowIsAvailable();
        }
    }

    protected function ensureNextRowIsAvailable()
    {
        if ($this->row) {
            $this->currentPosition++;
        } else {
            $this->query = array_shift($this->queries);

            if ($this->query) {
                $this->start = 0;
                $this->initQuery();
            } else {
                /**
                 * No more results, exit
                 */
                $this->cleanupResults();
            }
        }
    }

    protected function cleanupResults()
    {
        $this->rows->closeCursor();
        /**
         * Cleanup variable to free memory
         */
        $this->gcHelper->cleanVariable($this->rows);
        $this->rows = null;
        $this->gcHelper->cleanup();
    }
}
