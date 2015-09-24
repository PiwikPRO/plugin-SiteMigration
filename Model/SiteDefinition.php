<?php
/**
 * Abraxas Medien
 * Developer: Adrian Tello
 * Date: 21.09.15
 * Time: 16:12
 */

namespace Piwik\Plugins\SiteMigration\Model;

use Piwik\Plugins\SiteMigration\Helper\DBHelper;

class SiteDefinition {

	protected $siteId;

	protected $dbHelper;

	function __construct($siteId, DBHelper $dbHelper) {
		$this->siteId = $siteId;
		$this->dbHelper = $dbHelper;
	}

	/**
	 * @return mixed
	 */
	public function getSiteId() {
		return $this->siteId;
	}

	/**
	 * @return mixed
	 */
	public function getDbHelper() {
		return $this->dbHelper;
	}
}