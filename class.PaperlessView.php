<?php
/**
 * Implementation of a paperless view
 *
 * @package    SeedDMS
 * @subpackage paperless
 * @license    GPL3
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2023 Uwe Steinmann
 */

/**
 * Class to represent a paperless view
 *
 */
class SeedDMS_PaperlessView { /* {{{ */
	/**
	 * @var integer unique id of object
	 */
	protected $_id;

	/**
	 * @var object user owning the document link
	 */
	protected $_user;

	/**
	 * @var string view data
	 */
	protected $_view;

	/**
	 * @var object back reference to document management system
	 */
	public $_dms;

	function __construct($id, $user, $view) { /* {{{ */
		$this->_id = $id;
		$this->_dms = null;
		$this->_user = $user;
		$this->_view = $view;
	} /* }}} */

	protected static function __getInstance($queryStr, $dms) { /* {{{ */
		$db = $dms->getDB();
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;
		if (count($resArr) != 1)
			return false;
		$resArr = $resArr[0];

		if(!$user = $dms->getUser($resArr['userID']))
			return false;

		$view = new self($resArr["id"], $user, json_decode($resArr["view"], true));
		$view->setDMS($dms);
		return $view;
	} /* }}} */

	public static function getInstance($id, $dms) { /* {{{ */
		$queryStr = "SELECT * FROM `tblPaperlessView` WHERE `id` = " . (int) $id;
		return self::__getInstance($queryStr, $dms);
	} /* }}} */

	protected static function __getAllInstances($queryStr, $dms) { /* {{{ */
		$db = $dms->getDB();
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;

		$views = array();
		foreach($resArr as $rec) {
			$user = $dms->getUser($rec['userID']);

			$view = new self($rec["id"], $user, json_decode($rec["view"], true));
			$view->setDMS($dms);
			$views[] = $view;
		}
		return $views;
	} /* }}} */

	public static function getAllInstances($user, $dms) { /* {{{ */
		$db = $dms->getDB();
		$queryStr = "SELECT * FROM `tblPaperlessView`";
		if($user) {
			$queryStr .= " WHERE";
			$queryStr .= " `userID` = " . (int) $user->getID();
		}
		return self::__getAllInstances($queryStr, $dms);
	} /* }}} */

	/*
	 * Set dms this object belongs to.
	 *
	 * Each object needs a reference to the dms it belongs to. It will be
	 * set when the object is created.
	 * The dms has a references to the currently logged in user
	 * and the database connection.
	 *
	 * @param object $dms reference to dms
	 */
	public function setDMS($dms) { /* {{{ */
		$this->_dms = $dms;
	} /* }}} */

	public function getView() { /* {{{ */
		$this->_view['id'] = (int) $this->_id;
		return $this->_view;
	} /* }}} */

	public function setView($view) { /* {{{ */
		$this->_view = $view;
	} /* }}} */

	/*
	 * Add a new to the database.
	 *
	 * @param object $dms reference to dms
	 */
	public function save() { /* {{{ */
		$db = $this->_dms->getDB();
		if(!$this->_id) {
			$queryStr = "INSERT INTO `tblPaperlessView` (`userID`, `view`) VALUES (".$this->_user->getID().", ".$db->qstr(json_encode($this->_view)).")";
			if (!$db->getResult($queryStr)) {
				return false;
			}
			$this->_id = $db->getInsertID();
		} else {
			$queryStr = "UPDATE `tblPaperlessView` SET `view`=".$db->qstr(json_encode($this->_view))." WHERE `id`=".$this->_id;
			if (!$db->getResult($queryStr)) {
				return false;
			}
		}

    return self::getInstance($this->_id, $this->_dms);
	} /* }}} */

	/**
	 * Remove a saved view
	 *
	 * @return boolean true on success, otherwise false
	 */
	function remove() { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "DELETE FROM `tblPaperlessView` WHERE `id` = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			return false;
		}

		return true;
	} /* }}} */

} /* }}} */

