<?php


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

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current()
    {
        return $this->row;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        $this->loadNextRow();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        return $this->start + $this->currentPosition;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid()
    {
        return ($this->row != null);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
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