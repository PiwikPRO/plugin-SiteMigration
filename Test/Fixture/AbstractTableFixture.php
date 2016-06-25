<?php
/**
 * Abraxas Medien
 * Developer: Adrian Tello
 * Date: 01.10.15
 * Time: 14:14
 */

namespace Piwik\Plugins\SiteMigration\Test\Fixture;

use Piwik\Common;
use Piwik\Db;
use Piwik\Tests\Framework\Fixture;

abstract class AbstractTableFixture extends Fixture {

	public $data = array();

	public function setUp()
	{
		parent::setUp();

		$this->compatData();
		$this->insertTables($this->data);
	}

	protected function compatData()
	{
		//Remove exclude_unknown_urls if using a core version lower than 2.15.0-b3
		if (!file_exists(PIWIK_DOCUMENT_ROOT . '/core/Updates/2.15.0-b3.php')) {
			foreach ($this->data['site']['data'] as &$row) {
				unset($row['exclude_unknown_urls']);
			}
		}
	}

	protected function insertTables()
	{
		foreach ($this->data as $tableName => $table) {
			$prefixedTable = Common::prefixTable($tableName);
			foreach ($table['data'] as $row) {
				$placeholders = array_map(function () { return "?"; }, $row);
				$columns = array_keys($row);
				foreach ($columns as &$column){
					$column = '`' . $column . '`';
				}

				$sql = "INSERT INTO $prefixedTable (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
				Db::query($sql, array_values($row));
			}
		}
	}

}