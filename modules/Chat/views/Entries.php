<?php

/**
 * Chat Entries View Class.
 *
 * @package   View
 *
 * @copyright YetiForce Sp. z o.o
 * @license   YetiForce Public License 3.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author    Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 * @author    Arkadiusz Adach <a.adach@yetiforce.com>
 */
class Chat_Entries_View extends \App\Controller\View
{
	use \App\Controller\ExposeMethod;

	/**
	 * Record model.
	 *
	 * @var \Vtiger_Record_Model
	 */
	private $recordModel;

	/**
	 * Constructor with a list of allowed methods.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->exposeMethod('send');
		$this->exposeMethod('get');
		$this->exposeMethod('getMore');
		$this->exposeMethod('search');
		$this->exposeMethod('history');
		$this->exposeMethod('unread');
	}

	/**
	 * {@inheritdoc}
	 */
	public function checkPermission(\App\Request $request)
	{
		$currentUserPriviligesModel = Users_Privileges_Model::getCurrentUserPrivilegesModel();
		if (!$currentUserPriviligesModel->hasModulePermission($request->getModule())) {
			throw new \App\Exceptions\NoPermitted('ERR_NOT_ACCESSIBLE', 406);
		}
		if ($request->has('roomType') && !$request->has('recordId')) {
			throw new \App\Exceptions\NoPermittedToRecord('ERR_NOT_ALLOWED_VALUE', 406);
		} elseif ($request->has('roomType') && $request->has('recordId')) {
			$recordId = $request->getInteger('recordId');
			switch ($request->getByType('roomType')) {
				case 'crm':
					$this->recordModel = Vtiger_Record_Model::getInstanceById($recordId);
					if (!$this->recordModel->isViewable()) {
						throw new \App\Exceptions\NoPermittedToRecord('ERR_NO_PERMISSIONS_FOR_THE_RECORD', 406);
					}
					break;
				case 'group':
					if (!isset(\App\Fields\Owner::getInstance('CustomView')->getGroups(false)[$recordId])) {
						throw new \App\Exceptions\NoPermittedToRecord('ERR_NO_PERMISSIONS_FOR_THE_RECORD', 406);
					}
					break;
			}
		}
	}

	/**
	 * Send message function.
	 *
	 * @param \App\Request $request
	 */
	public function send(\App\Request $request)
	{
		$chat = \App\Chat::getInstance($request->getByType('roomType'), $request->getInteger('recordId'));
		if (!$chat->isRoomExists()) {
			throw new \App\Exceptions\IllegalValue('ERR_NOT_ALLOWED_VALUE', 406);
		}
		$chat->addMessage($request->get('message'));
		$chatEntries = $chat->getEntries($request->isEmpty('mid') ? null : $request->getInteger('mid'));
		$viewer = $this->getViewer($request);
		$viewer->assign('CHAT_ENTRIES', $chatEntries);
		$viewer->assign('CURRENT_ROOM', \App\Chat::getCurrentRoom());
		$viewer->assign('SHOW_MORE_BUTTON', count($chatEntries) > \AppConfig::module('Chat', 'CHAT_ROWS_LIMIT'));
		echo $viewer->view('Entries.tpl', $request->getModule(), true);
	}

	/**
	 * Get messages from chat.
	 *
	 * @param \App\Request $request
	 *
	 * @throws \App\Exceptions\IllegalValue
	 */
	public function get(\App\Request $request)
	{
		if ($request->has('roomType') && $request->has('recordId')) {
			$roomType = $request->getByType('roomType');
			$recordId = $request->getInteger('recordId');
			if (!$request->getBoolean('viewForRecord')) {
				\App\Chat::setCurrentRoom($roomType, $recordId);
			}
		} else {
			$currentRoom = \App\Chat::getCurrentRoom();
			if (!$currentRoom || !isset($currentRoom['roomType']) || !isset($currentRoom['recordId'])) {
				throw new \App\Exceptions\IllegalValue('ERR_NOT_ALLOWED_VALUE', 406);
			}
			$roomType = $currentRoom['roomType'];
			$recordId = $currentRoom['recordId'];
		}
		$chat = \App\Chat::getInstance($roomType, $recordId);
		if (!$chat->isRoomExists()) {
			return;
		}
		$chatEntries = $chat->getEntries($request->has('lastId') ? $request->getInteger('lastId') : null);
		$numberOfEntries = count($chatEntries);
		if ($request->has('lastId') && !$numberOfEntries) {
			return;
		}
		$viewer = $this->getViewer($request);
		$viewer->assign('CURRENT_ROOM', \App\Chat::getCurrentRoom());
		$viewer->assign('CHAT_ENTRIES', $chatEntries);
		$viewer->assign('SHOW_MORE_BUTTON', $numberOfEntries > \AppConfig::module('Chat', 'CHAT_ROWS_LIMIT'));
		if (!$request->has('lastId')) {
			$viewer->assign('PARTICIPANTS', $chat->getParticipants());
		}
		$viewer->view('Entries.tpl', $request->getModule());
	}

	/**
	 * Get more messages from chat.
	 *
	 * @param \App\Request $request
	 *
	 * @throws \App\Exceptions\AppException
	 * @throws \App\Exceptions\IllegalValue
	 * @throws \yii\db\Exception
	 */
	public function getMore(\App\Request $request)
	{
		$chat = \App\Chat::getInstance($request->getByType('roomType'), $request->getInteger('recordId'));
		$chatEntries = $chat->getEntries($request->getInteger('lastId'), '<=');
		$viewer = $this->getViewer($request);
		$viewer->assign('CURRENT_ROOM', \App\Chat::getCurrentRoom());
		$viewer->assign('CHAT_ENTRIES', $chatEntries);
		$viewer->assign('SHOW_MORE_BUTTON', count($chatEntries) > \AppConfig::module('Chat', 'CHAT_ROWS_LIMIT'));
		$viewer->view('Entries.tpl', $request->getModule());
	}

	/**
	 * Search meassages.
	 *
	 * @param \App\Request $request
	 *
	 * @throws \App\Exceptions\IllegalValue
	 */
	public function search(\App\Request $request)
	{
		$chat = \App\Chat::getInstance($request->getByType('roomType'), $request->getInteger('recordId'));
		$searchVal = $request->getByType('searchVal', 'Text');
		if (!$request->isEmpty('mid')) {
			$chatEntries = $chat->getEntries($request->getInteger('mid'), '<=', $searchVal);
		} else {
			$chatEntries = $chat->getEntries(null, '>', $searchVal);
		}
		$viewer = $this->getViewer($request);
		$viewer->assign('CURRENT_ROOM', \App\Chat::getCurrentRoom());
		$viewer->assign('CHAT_ENTRIES', $chatEntries);
		$viewer->assign('SHOW_MORE_BUTTON', count($chatEntries) > \AppConfig::module('Chat', 'CHAT_ROWS_LIMIT'));
		$viewer->view('Entries.tpl', $request->getModule());
	}

	/**
	 * Show chat for record.
	 *
	 * @param \App\Request $request
	 *
	 * @throws \App\Exceptions\IllegalValue
	 * @throws \App\Exceptions\NoPermittedToRecord
	 *
	 * @return \html
	 */
	public function getForRecord(\App\Request $request)
	{
		$recordModel = Vtiger_Record_Model::getInstanceById($request->getInteger('record'));
		if (!$recordModel->isViewable()) {
			throw new \App\Exceptions\NoPermittedToRecord('ERR_NO_PERMISSIONS_FOR_THE_RECORD', 406);
		}
		$chat = \App\Chat::getInstance('crm', $recordModel->getId());
		$chatEntries = $chat->getEntries();
		$viewer = $this->getViewer($request);
		$viewer->assign('CHAT_ENTRIES', $chatEntries);
		$viewer->assign('CURRENT_ROOM', ['roomType' => 'crm', 'recordId' => $recordModel->getId()]);
		$viewer->assign('PARTICIPANTS', $chat->getParticipants());
		$viewer->assign('SHOW_MORE_BUTTON', count($chatEntries) > \AppConfig::module('Chat', 'CHAT_ROWS_LIMIT'));
		$viewer->assign('MODULE_NAME', 'Chat');
		$viewer->assign('CHAT', $chat);
		$viewer->assign('VIEW_FOR_RECORD', true);
		return $viewer->view('Detail/Chat.tpl', 'Chat', true);
	}

	/**
	 * Get history.
	 *
	 * @param \App\Request $request
	 *
	 * @throws \App\Exceptions\AppException
	 * @throws \App\Exceptions\IllegalValue
	 */
	public function history(\App\Request $request)
	{
		$chat = \App\Chat::getInstance();
		$groupHistory = $request->getByType('groupHistory', 2);
		if ($request->isEmpty('mid')) {
			$chatEntries = $chat->getHistoryByType($groupHistory);
		} else {
			$chatEntries = $chat->getHistoryByType($groupHistory, $request->getInteger('mid'));
		}
		$viewer = $this->getViewer($request);
		$viewer->assign('CURRENT_ROOM', \App\Chat::getCurrentRoom());
		$viewer->assign('CHAT_ENTRIES', $chatEntries);
		$viewer->assign('SHOW_MORE_BUTTON', count($chatEntries) > \AppConfig::module('Chat', 'CHAT_ROWS_LIMIT'));
		$viewer->view('Entries.tpl', $request->getModule());
	}

	/**
	 * Get unread messages.
	 *
	 * @param \App\Request $request
	 */
	public function unread(\App\Request $request)
	{
		$viewer = $this->getViewer($request);
		$viewer->view('Unread.tpl', $request->getModule());
	}

	/**
	 * {@inheritdoc}
	 */
	public function isSessionExtend()
	{
		return false;
	}
}
