<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\SiteMigration\Helper;


use Piwik\Db;

class DBHelper
{
    protected $adapter;

    protected $config;

    protected $inserts = array();

    function __construct(\Zend_Db_Adapter_Abstract $adapter, $config)
    {
        $this->adapter = $adapter;
        $this->config  = $config;

    }

    public function getInsertSQL($table, $values)
    {
        $vals = array();
        foreach ($values as $key => $val) {
            $vals['`' . $key . '`'] = $this->adapter->quote($val);
        }

        return "INSERT INTO " . $this->prefixTable($table) . '(' . implode(
            ', ',
            array_keys($vals)
        ) . ') VALUES (' . implode(', ', $vals) . ')';
    }

    public function executeInsert($table, $values)
    {
        $this->adapter->insert($this->prefixTable($table), $values);
    }

    public function insert($table, $values)
    {
        if (!array_key_exists($table, $this->inserts)) {
            $this->inserts[$table] = array();
        }

        $this->inserts[$table][] = $values;
    }

    public function flush()
    {
        $this->flushInserts();
    }

    public function flushInserts()
    {
        foreach ($this->inserts as $table => $inserts) {
            $query = 'INSERT INTO ' . $this->prefixTable($table) . ' (`' . implode('`, `', array_keys($inserts[0])) . '`) VALUES ';

            for ($i = 0; $i < count($inserts); $i++) {
                if ($i > 0) {
                    $query .= ', ';
                }

                /**
                 * Workaround for php 5.3
                 */
                $dbHelper = &$this;

                $values = array_map(function (&$item) use ($dbHelper){
                        return $dbHelper->adapter->quote($item);
                    },
                    $inserts[$i]);

                $query .= '(' . implode(', ', $values) . ')';
            }

            $this->adapter->query($query);
        }
        /**
         * Clear inserts
         */
        $this->inserts = array();
    }

    public function getDBName()
    {
        return $this->config['dbname'];
    }

    public function acquireLock($lockName, $maxRetries = 30)
    {
        $sql = 'SELECT GET_LOCK(?, 1)';

        while ($maxRetries > 0) {
            if ($this->adapter->fetchOne($sql, array($lockName)) == '1') {
                return true;
            }
            $maxRetries--;
        }
        return false;
    }

    public function releaseLock($lockName)
    {
        $sql = 'SELECT RELEASE_LOCK(?)';

        return $this->adapter->fetchOne($sql, array($lockName)) == '1';
    }

    public function prefixTable($table)
    {
        return $this->config['tables_prefix'] . $table;
    }

    /**
     * @return \Zend_Db_Adapter_Abstract
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * @return mixed
     */
    public function getConfig()
    {
        return $this->config;
    }

    public function lastInsertId($tableName = null, $primaryKey = null)
    {
        return $this->adapter->lastInsertId($tableName, $primaryKey);
    }
} 