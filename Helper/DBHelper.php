<?php


namespace Piwik\Plugins\SiteMigrator\Helper;


use Piwik\Common;
use Piwik\Config;
use Piwik\Db;

class DBHelper
{
    protected $adapter;

    protected $config;

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