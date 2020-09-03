<?php
require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';

class MyAccount_MyList extends MyAccount {
	function __construct(){
		$this->requireLogin = false;
		parent::__construct();
	}
	function launch() {
		global $interface;

		// Fetch List object
		$listId = $_REQUEST['id'];
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
		$list = new UserList();
		$list->id = $listId;

		//If the list does not exist, create a new My Favorites List
		if (!$list->find(true)){
			$list = new UserList();
			$list->user_id = UserAccount::getActiveUserId();
			$list->public = false;
			$list->title = "My Favorites";
		}

		// Ensure user has privileges to view the list
		if (!isset($list) || (!$list->public && !UserAccount::isLoggedIn())) {
			require_once ROOT_DIR . '/services/MyAccount/Login.php';
			$loginAction = new MyAccount_Login();
			$loginAction->launch();
			exit();
		}
		if (!$list->public && $list->user_id != UserAccount::getActiveUserId()) {
			//Allow the user to view if they are admin
			if (!UserAccount::isLoggedIn() || !UserAccount::userHasPermission('Edit All Lists')) {
				$this->display('invalidList.tpl', 'Invalid List');
				return;
			}
		}

		//List Notes are created as part of bulk add to list
		if (isset($_SESSION['listNotes'])){
			$interface->assign('notes', $_SESSION['listNotes']);
			unset($_SESSION['listNotes']);
		}

		//Perform an action on the list, but verify that the user has permission to do so.
		$userCanEdit = false;
		$userObj = UserAccount::getActiveUserObj();
		if ($userObj != false){
			$userCanEdit = $userObj->canEditList($list);
		}

		if ($userCanEdit && (isset($_REQUEST['myListActionHead']) || isset($_REQUEST['myListActionItem']) || isset($_GET['delete']))){
			if (isset($_REQUEST['myListActionHead']) && strlen($_REQUEST['myListActionHead']) > 0){
				$actionToPerform = $_REQUEST['myListActionHead'];
				if ($actionToPerform == 'makePublic'){
					$list->public = 1;
					$list->update();
				}elseif ($actionToPerform == 'makePrivate'){
					$list->public = 0;
					$list->update();
				}elseif ($actionToPerform == 'saveList'){
					$list->title = $_REQUEST['newTitle'];
					$list->description = strip_tags($_REQUEST['newDescription']);
					$list->update();
				}elseif ($actionToPerform == 'deleteList'){
					$list->delete();

					header("Location: /MyAccount/Home");
					die();
				}elseif ($actionToPerform == 'bulkAddTitles'){
					$notes = $this->bulkAddTitles($list);
					$_SESSION['listNotes'] = $notes;
				}
			}elseif (isset($_REQUEST['myListActionItem']) && strlen($_REQUEST['myListActionItem']) > 0){
				$actionToPerform = $_REQUEST['myListActionItem'];

				if ($actionToPerform == 'deleteMarked'){
					//get a list of all titles that were selected
					$itemsToRemove = $_REQUEST['selected'];
					foreach ($itemsToRemove as $id => $selected){
						//add back the leading . to get the full bib record
						$list->removeListEntry($id);
					}
				}elseif ($actionToPerform == 'deleteAll'){
					$list->removeAllListEntries();
				}
				$list->update();
			}elseif (isset($_REQUEST['delete'])) {
				$recordToDelete = $_REQUEST['delete'];
				$list->removeListEntry($recordToDelete);
				$list->update();
			}

			//Redirect back to avoid having the parameters stay in the URL.
			header("Location: /MyAccount/MyList/{$list->id}");
			die();
		}

		// Send list to template so title/description can be displayed:
		$interface->assign('userList', $list);
		$interface->assign('listSelected', $list->id);

		// Create a handler for displaying favorites and use it to assign
		// appropriate template variables:
		$interface->assign('allowEdit', $userCanEdit);

		//Determine the sort options
		$activeSort = $list->defaultSort;
		if (isset($_REQUEST['sort']) && array_key_exists($_REQUEST['sort'], UserList::getSortOptions())){
			$activeSort = $_REQUEST['sort'];
		}
		if (empty($activeSort)) {
			$activeSort = 'dateAdded';
		}
		//Set the default sort (for people other than the list editor to match what the editor does)
		if ($userCanEdit && $activeSort != $list->defaultSort){
			$list->defaultSort = $activeSort;
			$list->update();
		}

		$listEntries = $list->getListEntries($activeSort);
		$allListEntries = $listEntries['listEntries'];

		$this->buildListForDisplay($list, $allListEntries, $userCanEdit, $activeSort);

		$this->display('../MyAccount/list.tpl', isset($list->title) ? $list->title : translate('My List'), 'Search/home-sidebar.tpl', false);
	}

	/**
	 * Assign all necessary values to the interface.
	 *
	 * @access  public
	 * @param UserList $list
	 * @param $allEntries
	 * @param bool $allowEdit
	 * @param string $sortName
	 */
	public function buildListForDisplay(UserList $list, $allEntries, $allowEdit = false, $sortName = 'dateAdded')
	{
		global $interface;

		$recordsPerPage = isset($_REQUEST['pageSize']) && (is_numeric($_REQUEST['pageSize'])) ? $_REQUEST['pageSize'] : 20;
		$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
		$startRecord = ($page - 1) * $recordsPerPage;
		if ($startRecord < 0){
			$startRecord = 0;
		}
		$endRecord = $page * $recordsPerPage;
		if ($endRecord > count($allEntries)){
			$endRecord = count($allEntries);
		}
		$pageInfo = array(
			'resultTotal' => count($allEntries),
			'startRecord' => $startRecord,
			'endRecord'   => $endRecord,
			'perPage'     => $recordsPerPage
		);

		$queryParams = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
		if ($queryParams == null){
			$queryParams = [];
		}else{
			$queryParamsTmp = explode("&", $queryParams);
			$queryParams = [];
			foreach ($queryParamsTmp as $param) {
				list($name, $value) = explode("=", $param);
				if ($name != 'sort'){
					$queryParams[$name] = $value;
				}
			}
		}
		$sortOptions = array(
			'title' => [
				'desc' => 'Title',
				'selected' => $sortName == 'title',
				'sortUrl' => "/MyAccount/MyList/{$list->id}?" . http_build_query(array_merge($queryParams, ['sort' => 'title']))
			],
			'dateAdded' => [
				'desc' => 'Date Added',
				'selected' => $sortName == 'dateAdded',
				'sortUrl' => "/MyAccount/MyList/{$list->id}?" . http_build_query(array_merge($queryParams, ['sort' => 'dateAdded']))
			],
			'recentlyAdded' => [
				'desc' => 'Recently Added',
				'selected' => $sortName == 'recentlyAdded',
				'sortUrl' => "/MyAccount/MyList/{$list->id}?" . http_build_query(array_merge($queryParams, ['sort' => 'recentlyAdded']))
			],
			'custom' => [
				'desc' => 'User Defined',
				'selected' => $sortName == 'custom',
				'sortUrl' => "/MyAccount/MyList/{$list->id}?" . http_build_query(array_merge($queryParams, ['sort' => 'custom']))
			],
		);

		$interface->assign('sortList', $sortOptions);
		$interface->assign('userSort', ($sortName == 'custom')); // switch for when users can sort their list

		$resourceList = $list->getListRecords($startRecord , $recordsPerPage, $allowEdit, 'html');
		$interface->assign('resourceList', $resourceList);

		// Set up paging of list contents:
		$interface->assign('recordCount', $pageInfo['resultTotal']);
		$interface->assign('recordStart', $pageInfo['startRecord']);
		$interface->assign('recordEnd',   $pageInfo['endRecord']);
		$interface->assign('recordsPerPage', $pageInfo['perPage']);

		$link = $_SERVER['REQUEST_URI'];
		if (preg_match('/[&?]page=/', $link)){
			$link = preg_replace("/page=\\d+/", "page=%d", $link);
		}else if (strpos($link, "?") > 0){
			$link .= "&page=%d";
		}else{
			$link .= "?page=%d";
		}
		$options = array('totalItems' => $pageInfo['resultTotal'],
			'perPage' => $pageInfo['perPage'],
			'fileName' => $link,
			'append'    => false);
		require_once ROOT_DIR . '/sys/Pager.php';
		$pager = new Pager($options);
		$interface->assign('pageLinks', $pager->getLinks());

	}

	function bulkAddTitles($list){
		$numAdded = 0;
		$notes = array();
		$titlesToAdd = $_REQUEST['titlesToAdd'];
		$titleSearches[] = preg_split("/\\r\\n|\\r|\\n/", $titlesToAdd);

		foreach ($titleSearches[0] as $titleSearch){
			$titleSearch = trim($titleSearch);
			if (!empty($titleSearch)) {
				$_REQUEST['lookfor'] = $titleSearch;
				$_REQUEST['searchIndex']    = 'Keyword';
				$searchObject        = SearchObjectFactory::initSearchObject();
				$searchObject->setLimit(1);
				$searchObject->init();
				$searchObject->clearFacets();
				$results = $searchObject->processSearch(false, false);
				if ($results['response'] && $results['response']['numFound'] >= 1) {
					$firstDoc = $results['response']['docs'][0];
					//Get the id of the document
					$id = $firstDoc['id'];
					$numAdded++;
					$userListEntry = new UserListEntry();
					$userListEntry->listId = $list->id;
					$userListEntry->source = 'GroupedWork';
					$userListEntry->sourceId = $id;
					$existingEntry = false;
					if ($userListEntry->find(true)) {
						$existingEntry = true;
					}
					$userListEntry->notes = '';
					$userListEntry->dateAdded = time();
					if ($existingEntry) {
						$userListEntry->update();
					} else {
						$userListEntry->insert();
					}
				} else {
					$notes[] = "Could not find a title matching " . $titleSearch;
				}
			}
		}

		//Update solr
		$list->update();

		if ($numAdded > 0){
			$notes[] = "Added $numAdded titles to the list";
		} elseif ($numAdded === 0) {
			$notes[] = 'No titles were added to the list';
		}

		return $notes;
	}

	function getBreadcrumbs()
	{
		$breadcrumbs = [];
		$breadcrumbs[] = new Breadcrumb('/MyAccount/Home', 'My Account');
		if (UserAccount::isLoggedIn()){
			$breadcrumbs[] = new Breadcrumb('/MyAccount/Lists', 'Lists');
		}
		$breadcrumbs[] = new Breadcrumb('', 'List');
		return $breadcrumbs;
	}
}