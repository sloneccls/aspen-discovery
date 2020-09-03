<?php

require_once ROOT_DIR . '/sys/DB/DataObject.php';

class Role extends DataObject
{
	public $__table = 'roles';// table name
	public $__primaryKey = 'roleId';
	public $roleId;
	public $name;
	public $description;
	private $_permissions;

	function keys()
	{
		return array('roleId');
	}

	static function getObjectStructure()
	{
		$permissionsList = [];
		return [
			'roleId' => ['property' => 'roleId', 'type' => 'label', 'label' => 'Role Id', 'description' => 'The unique id of the role within the database'],
			'name' => ['property' => 'name', 'type' => 'text', 'label' => 'Name', 'maxLength' => 50, 'description' => 'The full name of the role.'],
			'description' => ['property' => 'name', 'type' => 'text', 'label' => 'Name', 'maxLength' => 100, 'description' => 'The full name of the role.'],

			'permissions' => [
				'property' => 'permissions',
				'type' => 'multiSelect',
				'listStyle' => 'checkboxSimple',
				'label' => 'Permissions',
				'description' => 'Define permissions for the role',
				'values' => $permissionsList,
				'forcesReindex' => false
			],
		];
	}

	static function getLookup()
	{
		$role = new Role();
		$role->orderBy('name');
		$role->find();
		$roleList = [];
		while ($role->fetch()) {
			$roleList[$role->roleId] = $role->name . ' - ' . $role->description;
		}
		return $roleList;
	}

	function getPermissions(){
		if ($this->_permissions == null){
			$this->_permissions = [];
			$loadDefaultPermissions = false;
			try{
				require_once ROOT_DIR . '/sys/Administration/Permission.php';
				require_once ROOT_DIR . '/sys/Administration/RolePermissions.php';
				$rolePermissions = new RolePermissions();
				$rolePermissions->roleId = $this->roleId;
				$rolePermissions->find();
				while ($rolePermissions->fetch()){
					$permission = new Permission();
					$permission->id = $rolePermissions->permissionId;
					if ($permission->find(true)){
						$this->_permissions[] = $permission->name;
					}
				}
			}catch (Exception $e){
				$loadDefaultPermissions = true;
			}
			//If we don't have permissions in the database, load defaults (this happens during conversion)
			if ($loadDefaultPermissions || count($this->_permissions) == 0){
				$this->_permissions = $this->getDefaultPermissions();
			}
		}
		return $this->_permissions;
	}

	function setActivePermissions($permissions){
		$this->clearOneToManyOptions('RolePermissions', 'roleId');
		foreach ($permissions as $permissionId){
			require_once ROOT_DIR . '/sys/Administration/RolePermissions.php';
			$rolePermission = new RolePermissions();
			$rolePermission->roleId = $this->roleId;
			$rolePermission->permissionId = $permissionId;
			$rolePermission->insert();
		}
	}

	function hasPermission($permission){
		return in_array($permission, $this->getPermissions());
	}

	public function getDefaultPermissions()
	{
		switch ($this->name){
		case 'opacAdmin':
			return [
				'Administer Account Profiles',
				'Administer All Browse Categories',
				'Administer All Collection Spotlights',
				'Administer All Grouped Work Display Settings',
				'Administer All Grouped Work Facets',
				'Administer All Layout Settings',
				'Administer All Libraries',
				'Administer All Locations',
				'Administer All Placards',
				'Administer All Themes',
				'Administer Axis 360',
				'Administer Cloud Library',
				'Administer EBSCO EDS',
				'Administer Genealogy',
				'Administer Hoopla',
				'Administer IP Addresses',
				'Administer Indexing Profiles',
				'Administer Islandora Archive',
				'Administer Languages',
				'Administer Library Calendar Settings',
				'Administer Loan Rules',
				'Administer Modules',
				'Administer OverDrive',
				'Administer Patron Types',
				'Administer RBdigital',
				'Administer SendGrid',
				'Administer Side Loads',
				'Administer System Variables',
				'Administer Third Party Enrichment API Keys',
				'Administer Translation Maps',
				'Administer Website Indexing Settings',
				'Administer Wikipedia Integration',
				'Block Patron Account Linking',
				'Download MARC Records',
				'Edit All Lists',
				'Force Reindexing of Records',
				'Include Lists In Search Results',
				'Import Materials Requests',
				'Manually Group and Ungroup Works',
				'Moderate User Reviews',
				'Open Archives',
				'Run Database Maintenance',
				'Set Grouped Work Display Information',
				'Submit Ticket',
				'Translate Aspen',
				'Upload Covers',
				'Upload PDFs',
				'Upload Supplemental Files',
				'View Archive Authorship Claims',
				'View Archive Material Requests',
				'View Dashboards',
				'View Help Manual',
				'View Indexing Logs',
				'View ILS records in native OPAC',
				'View ILS records in native Staff Client',
				'View Islandora Archive Usage',
				'View New York Times Lists',
				'View Offline Holds Report',
				'View OverDrive Test Interface',
				'View Release Notes',
				'View System Reports',
			];
		case 'userAdmin':
			return [
				'Administer Permissions',
				'Administer Users',
				'View Help Manual',
				'View Release Notes',
			];
		case 'libraryAdmin':
			return [
				'Administer Home Library Locations',
				'Administer Home Library',
				'Administer Home Location',
				'Administer Library Browse Categories',
				'Administer Library Collection Spotlights',
				'Administer Library Grouped Work Display Settings',
				'Administer Library Grouped Work Facets',
				'Administer Library Layout Settings',
				'Administer Library Placards',
				'Administer Library Themes',
				'Block Patron Account Linking',
				'Submit Ticket',
				'View Help Manual',
				'View New York Times Lists',
				'View Offline Holds Report',
				'View Release Notes',
			];
		case 'libraryManager':
			return [
				'Administer Home Library Locations',
				'Administer Home Library',
				'Administer Library Browse Categories',
				'Administer Library Collection Spotlights',
				'Block Patron Account Linking',
				'View Help Manual',
				'View New York Times Lists',
				'View Release Notes',
			];
		case 'locationManager':
			return [
				'Administer Home Location',
				'Administer Library Browse Categories',
				'Administer Library Collection Spotlights',
				'Block Patron Account Linking',
				'View Help Manual',
				'View Release Notes',
			];
		case 'translator':
			return [
				'Administer Languages',
				'Translate Aspen',
				'View Help Manual',
				'View Release Notes',
			];
		case 'library_material_requests':
			return [
				'Administer Materials Requests',
				'Manage Library Materials Requests',
				'View Help Manual',
				'View Materials Requests Reports',
				'View Release Notes',
			];
		case 'superCataloger':
			return [
				'Administer Indexing Profiles',
				'Administer Loan Rules',
				'Administer Translation Maps',
				'Administer Wikipedia Integration',
				'Download MARC Records',
				'Force Reindexing of Records',
				'Manually Group and Ungroup Works',
				'Set Grouped Work Display Information',
				'Upload Covers',
				'Upload PDFs',
				'Upload Supplemental Files',
				'View Dashboards',
				'View Help Manual',
				'View ILS records in native OPAC',
				'View ILS records in native Staff Client',
				'View Indexing Logs',
				'View Release Notes',
			];
		case 'cataloging':
			return [
				'Administer Wikipedia Integration',
				'Download MARC Records',
				'Force Reindexing of Records',
				'Manually Group and Ungroup Works',
				'Upload Covers',
				'Upload PDFs',
				'Upload Supplemental Files',
				'View Help Manual',
				'View ILS records in native OPAC',
				'View ILS records in native Staff Client',
				'View Indexing Logs',
				'View Release Notes',
			];
		case 'archives':
			return [
				'Administer Islandora Archive',
				'View Archive Authorship Claims',
				'View Archive Material Requests',
				'View Help Manual',
				'View Release Notes',
			];
		case 'circulationReports':
			return [
				'View Offline Holds Report'
			];
		case 'contentEditor':
			return [
				'Administer Library Browse Categories',
				'Administer Library Collection Spotlights',
				'Administer Library Placards',
				'View New York Times Lists'
			];
		case 'genealogyContributor':
			return [
				'Administer Genealogy'
			];
		case 'listPublisher':
			return [
				'Include Lists In Search Results',
			];
		}
		return [];
	}
}