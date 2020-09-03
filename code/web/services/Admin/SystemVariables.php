<?php

require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';

class Admin_SystemVariables extends ObjectEditor{

	function getObjectType(){
		return 'SystemVariables';
	}
	function getToolName(){
		return 'SystemVariables';
	}
	function getPageTitle(){
		return 'System Variables';
	}
	function getAllObjects(){
		$variableList = array();

		$variable = new SystemVariables();
		$variable->find();
		while ($variable->fetch()){
			$variableList[$variable->id] = clone $variable;
		}
		return $variableList;
	}
	function getObjectStructure(){
		return SystemVariables::getObjectStructure();
	}
	function getPrimaryKeyColumn(){
		return 'name';
	}
	function getIdKeyColumn(){
		return 'id';
	}
	function getAllowableRoles(){
		return array('opacAdmin');
	}
	function canAddNew(){
		return count($this->getAllObjects()) == 0;
	}
	function canDelete(){
		return false;
	}

	function getBreadcrumbs()
	{
		$breadcrumbs = [];
		$breadcrumbs[] = new Breadcrumb('/Admin/Home', 'Administration Home');
		$breadcrumbs[] = new Breadcrumb('/Admin/Home#system_admin', 'System Administration');
		$breadcrumbs[] = new Breadcrumb('/Admin/SystemVariables', 'System Variables');
		return $breadcrumbs;
	}

	function getActiveAdminSection()
	{
		return 'system_admin';
	}

	function canView()
	{
		return UserAccount::userHasPermission('Administer System Variables');
	}
}