<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\DavContacts\Storages\Sabredav;

use Aurora\Modules\Contacts\Models\AddressBook;
use Sabre\CardDAV\Plugin;
use Sabre\DAV\MkCol;
use Sabre\DAV\PropPatch;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Storage extends \Aurora\Modules\DavContacts\Storages\Storage
{
	/**
	 * @var string
	 */
	public $Principal;

	/**
	 * @var \Aurora\Modules\StandardAuth\Classes\Account
	 */
//	protected $Account;

	/**
	 * @var $oApiUsersManager CApiUsersManager
	 */
	protected $ApiUsersManager;

	protected $aAddressBooksCache;
	protected $aContactItemsCache;
	protected $aGroupItemsCache;
	protected $ContactsCache;
	protected $InitialisedUserId;
//	protected $AccountsCache;

	/**
	 * 
	 * @param \Aurora\System\Managers\AbstractManager $oManager
	 */
	public function __construct(\Aurora\System\Managers\AbstractManager &$oManager)
	{
		parent::__construct($oManager);

//		$this->Account = null;

		$this->aAddressBooksCache = array();
		$this->aContactItemsCache = array();
		$this->aGroupItemsCache = array();

		$this->ContactsCache = array();
		$this->GroupsCache = array();
//		$this->AccountsCache = array();

		$this->CoreModuleDecorator = \Aurora\System\Api::GetModuleDecorator('Core');
	}

	/**
	 * @param \Aurora\Modules\StandardAuth\Classes\Account $oAccount
	 */
	public function InitByUser($oUser = null)
	{
		$bResult = false;

//		if ($oAccount && (!$this->Account || $this->Account->Email !== $oAccount->Email))
		if ($oUser)
		{
//			$this->Account = $oAccount;
			$this->aAddressBooksCache = array();
			$this->aContactItemsCache = array();
			$this->aGroupItemsCache = array();

			$this->ContactsCache = array();
			$this->GroupsCache = array();

//			\Afterlogic\DAV\Server::getInstance()->setAccount($oAccount);
//			$aPrincipalProperties = \Afterlogic\DAV\Backend::Principal()->getPrincipalByPath(\Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $oAccount->Email);
			$aPrincipalProperties = \Afterlogic\DAV\Backend::Principal()->getPrincipalByPath(\Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $oUser->PublicId);
			if ($aPrincipalProperties)
			{
				if (isset($aPrincipalProperties['uri']))
				{
					$this->Principal = $aPrincipalProperties['uri'];
				}
			}
			$bResult = true;
		}

//		if ($this->Account)
//		{
//			$bResult = true;
//		}

		return $bResult;
	}

//	protected function GetDefaultAccountByUserId($iUserId)
//	{
//		if (!isset($this->AccountsCache[$iUserId]))
//		{
//			$iAccountId = $this->ApiUsersManager->getDefaultAccountId($iUserId);
//			$oAccount = $this->ApiUsersManager->getAccountById($iAccountId);
//			$this->AccountsCache[$iUserId] = $oAccount;
//		}
//
//		return $this->AccountsCache[$iUserId];
//	}


	/**
	 * @param int $iUserId
	 */
	public function init($iUserId)
	{
		$bInitialized = true;
		if ($this->InitialisedUserId !== $iUserId)
		{
	//		$oAccount = $this->GetDefaultAccountByUserId($iUserId);
			$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserUnchecked($iUserId);
			$this->InitialisedUserId = $iUserId;
			$bInitialized = $this->InitByUser($oUser);
		}

		return $bInitialized;
	}

	/**
	 * @param int $iUserId
	 * @param mixed $mContactId
	 * @param string $sAddressBookName
	 * @return \Aurora\Modules\Contacts\Classes\Contact | false
	 */
	public function getContactById($iUserId, $mContactId, $sAddressBookName = \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME)
	{
		$oContact = false;
		
		$oContactItem = $this->getVCardObjectById($iUserId, $mContactId, $sAddressBookName);
		
		if ($oContactItem)
		{
			$sVCardData = $oContactItem->get();
			if ($sVCardData)
			{
				$oContact = new \Aurora\Modules\Contacts\Models\Contact();
				$oContact->InitFromVCardStr($iUserId, $sVCardData);
				$oContact->IdContact = $mContactId;
				$oContact->ETag = trim($oContactItem->getETag(), '"');
			}
		}

		return $oContact;
	}
	
	/**
	 * @param int $iUserId
	 * @param mixed $mContactId
	 * @param string $sAddressBookName
	 * @return \Aurora\Modules\Contacts\Classes\Contact | false
	 */
	public function getVCardObjectById($iUserId, $mContactId, $sAddressBookName = \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME)
	{
		$mResult = false;
		if($this->init($iUserId))
		{
			$oAddressBook = $this->getAddressBook($iUserId, $sAddressBookName);
			$mResult = $this->geItem($iUserId, $oAddressBook, $mContactId . '.vcf');

		}
		return $mResult;
	}	

	/**
	 * @param int $iUserId
	 * @param string $sEmail
	 * @return \Aurora\Modules\Contacts\Classes\Contact|bool
	 */
	public function getContactByEmail($iUserId, $sEmail)
	{
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param string $sContactStrId
	 * @param int $iSharedTenantId = null
	 * @return \Aurora\Modules\Contacts\Classes\Contact|bool
	 */
	public function getContactByStrId($iUserId, $sContactStrId, $iSharedTenantId = null)
	{
		return $this->getContactById($iUserId, $sContactStrId);
	}
	
	/**
	 * @param int $iUserId
	 * @param string $sContactStrId
	 * @return \Aurora\Modules\Contacts\Classes\Contact
	 */
	public function GetSuggestContactByEmail($iUserId, $sContactStrId)
	{
		return $this->getContactByEmail($iUserId, $sContactStrId);
	}	

	/**
	 * @param \Aurora\Modules\Contacts\Classes\Contact $oContact
	 * @return array|bool
	 */
	public function getContactGroupIds($oContact)
	{
		return $oContact->GroupIds;
	}

	/**
	 * @param int $iUserId
	 * @param mixed $mGroupId
	 * @return \Aurora\Modules\Contacts\Classes\Group
	 */
	public function getGroupById($iUserId, $mGroupId)
	{
		$bResult = false;

		if (!isset($this->GroupsCache[$mGroupId]))
		{
			if($this->init($iUserId))
			{
				$oAddressBook = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
				if ($oAddressBook)
				{
					if (!isset($this->aGroupItemsCache[$oAddressBook->getName()]))
					{
						$this->getItems($iUserId, $oAddressBook);
					}

					if (isset($this->aGroupItemsCache[$oAddressBook->getName()][$mGroupId]))
					{
						$bResult = new \Aurora\Modules\Contacts\Classes\Group();
						$bResult->IdUser = $iUserId;
						$bResult->IdGroup = $mGroupId;
						$bResult->IdGroupStr = $mGroupId;
						$bResult->Name =  $mGroupId;
					}
				}
			}
		}

		if (isset($this->GroupsCache[$mGroupId]))
		{
			$bResult = $this->GroupsCache[$mGroupId];
		}

		return $bResult;
	}

	/**
	 * @param int $iUserId
	 * @param string $sGroupStrId
	 * @return \Aurora\Modules\Contacts\Classes\Group
	 */
	public function getGroupByStrId($iUserId, $sGroupStrId)
	{
		return $this->getGroupById($iUserId, $sGroupStrId);
	}

	/**
	 * @param mixed $iUserId
	 * @param string $sName
	 * @return bool|array
	 */
	public function getAddressBook($iUserId, $sName)
	{
		$oAddressBook = false;
		if ($this->init($iUserId))
		{
			if (!isset($this->aAddressBooksCache[$iUserId][$sName]))
			{
				if ($sName === 'gab')
				{
					$this->aAddressBooksCache[$iUserId][$sName] = \Afterlogic\DAV\Server::getInstance()->tree->getNodeForPath($sName);
				}
				else
				{
					$oUserAddressBooks = new \Afterlogic\DAV\CardDAV\AddressBookRoot(
						\Afterlogic\DAV\Backend::Carddav(), $this->Principal);
						
					if ($oUserAddressBooks->childExists($sName))
					{
						$this->aAddressBooksCache[$iUserId][$sName] = $oUserAddressBooks->getChild($sName);
					}
				}
			}

			if (isset($this->aAddressBooksCache[$iUserId][$sName]))
			{
				$oAddressBook = $this->aAddressBooksCache[$iUserId][$sName];
			}
		}

		return $oAddressBook;
	}

	public function createAddressBook($iUserId, $sUri, $sName)
	{
		$this->init($iUserId);

		$oUserAddressBooks = new \Afterlogic\DAV\CardDAV\AddressBookRoot(
			\Afterlogic\DAV\Backend::Carddav(), $this->Principal);

		$oUserAddressBooks->createExtendedCollection(
			$sUri, 
			new MkCol(['{' . Plugin::NS_CARDDAV . '}addressbook'], 
			['{DAV:}displayname' => $sName])
		);

		return true;
	}

	public function updateAddressBook($iUserId, $sName, $sNewName)
	{
		$this->init($iUserId);

		$oUserAddressBooks = new \Afterlogic\DAV\CardDAV\AddressBookRoot(
			\Afterlogic\DAV\Backend::Carddav(), $this->Principal);
		if ($oUserAddressBooks->childExists($sName))
		{
			$oAddressBook = $oUserAddressBooks->getChild($sName);

			if ($oAddressBook) {
				$oPropPath = new PropPatch([
					'{DAV:}displayname' => $sNewName
				]);
				$oAddressBook->propPatch($oPropPath);
				$oPropPath->commit();
			}

			return true;
		}

		return false;
	}

	public function deleteAddressBook($iUserId, $sName)
	{
		$this->init($iUserId);

		$oUserAddressBooks = new \Afterlogic\DAV\CardDAV\AddressBookRoot(
			\Afterlogic\DAV\Backend::Carddav(), $this->Principal);
		if ($oUserAddressBooks->childExists($sName))
		{
			$oAddressBook = $oUserAddressBooks->getChild($sName);
			$oAddressBook->delete();

			return true;
		}

		return false;
	}

	/**
	 * @param int $iUserId
	 * @param \Afterlogic\DAV\CardDAV\AddressBook $oAddressBook
	 * @return bool|array
	 */
	protected function getObjectItems($iUserId, $oAddressBook)
	{
		$mResult = false;
		$sName = null;
		if ($oAddressBook)
		{
			$sName = $oAddressBook->getName();
			if (!isset($this->aContactItemsCache[$sName]))
			{
				$this->init($iUserId);

				$this->aContactItemsCache[$sName] = array();
				foreach ($oAddressBook->getChildren() as $oChild)
				{
					$this->aContactItemsCache[$sName][$oChild->getName()] = $oChild;
				}
			}

			$mResult = $this->aContactItemsCache[$sName];
		}
		return $mResult;
	}

	/**
	 * @param int $iUserId
	 * @param \Sabre\CardDAV\AddressBook $oAddressBook
	 * @param string $sId
	 * @return \Sabre\CardDAV\Card | bool
	 */
	protected function geItem($iUserId, $oAddressBook, $sId)
	{
		$bResult = false;
		$sName = null;
		if ($oAddressBook)
		{
			$sName = $oAddressBook->getName();
			if (isset($this->aContactItemsCache[$sName][$sId]))
			{
				$bResult = $this->aContactItemsCache[$sName][$sId];
			}
			else
			{
				if ($oAddressBook->childExists($sId))
				{
					$bResult = $oAddressBook->getChild($sId);
				}
			}
		}
		return $bResult;
	}

	/**
	 * @param int $iUserId
	 * @param \Afterlogic\DAV\CardDAV\AddressBook
	 * @param string $sSearch
	 * @param string $sFirstCharacter = ''
	 * @param int $sGroupId
	 * @return bool|array
	 */
	protected function getItems($iUserId, $oAddressBook, $sSearch = '', $sFirstCharacter = '', $sGroupId = null)
	{
		$aResult = array();
		$sName = null;
		if ($this->init($iUserId) && $oAddressBook)
		{

			$sName = $oAddressBook->getName();

			if (!empty($sGroupId))
			{
				unset($this->ContactsCache[$sName]);
			}

			if (isset($this->ContactsCache[$sName]))
			{
				$aResult = $this->ContactsCache[$sName];
			}
			else
			{
				$aItems = $this->getObjectItems($iUserId, $oAddressBook);

				foreach ($aItems as $oItem)
				{
					$sItemId = $oItem->getName();
					$oVCard = false;
					try
					{
						$oVCard = \Sabre\VObject\Reader::read($oItem->get());
					}
					catch(\Exception $ex)
					{
						\Aurora\System\Api::Log('SABREDAV: Invalid VCard with Id='.$sItemId);
					}
					if ($oVCard)
					{
						$sFullName = $sFirstName = $sLastName = $sTitle = $sNickName = '';
						if (isset($oVCard->FN))
						{
							$sFullName = (string)$oVCard->FN;
						}
						if (isset($oVCard->N))
						{
							$aNames = $oVCard->N->getParts();
							if (!empty($aNames[0]))
							{
								$sLastName = $aNames[0];
							}
							if (!empty($aNames[1]))
							{
								$sFirstName = $aNames[1];
							}
							if (!empty($aNames[3]))
							{
								$sTitle = $aNames[3];
							}
						}
						if (isset($oVCard->NICKNAME))
						{
							$sNickName = (string)$oVCard->NICKNAME;
						}

						$bFindEmail = false;
						if (isset($oVCard->EMAIL))
						{
							foreach($oVCard->EMAIL as $oEmail)
							{
								if (stripos((string)$oEmail, $sSearch) !== false)
								{
									$bFindEmail = true;
									break;
								}
							}
						}

						$sCategories = '';
						if (isset($oVCard->CATEGORIES))
						{
							$sCategories = (string)$oVCard->CATEGORIES;
							$aCategories = explode(',', (string)$oVCard->CATEGORIES);
							foreach($aCategories as $sCategory)
							{
								$sCategory = trim($sCategory);
								if (!empty($sCategory))
								{
									$this->aGroupItemsCache[$sName][$sCategory][$sItemId] = $sItemId;
								}
							}
						}

						if (!empty($sItemId) && (empty($sSearch) || stripos($sFullName, $sSearch) !== false ||
							stripos($sFirstName, $sSearch) !== false ||
							stripos($sLastName, $sSearch) !== false ||
							stripos($sNickName, $sSearch) !== false ||
							stripos($sTitle, $sSearch) !== false || $bFindEmail) &&
							(empty($sGroupId) || (!empty($sGroupId) && strpos($sCategories, $sGroupId) !== false)))
						{
							$oContactItem = new \Aurora\Modules\Contacts\Classes\ContactListItem();
							$oContactItem->InitBySabreCardDAVCard($oVCard);
							$oContactItem->Id = $sItemId;
							$oContactItem->ETag = $oItem->getETag();
							$aResult[] = $oContactItem;
							unset($oContactItem);
						}
					}
					unset($oVCard);
				}
				$this->ContactsCache[$sName] = $aResult;
			}
		}

		return $aResult;
	}

	/**
	 * @param int $iUserId
	 * @param \Afterlogic\DAV\CardDAV\AddressBook
	 * @return bool|array
	 */
	protected function initGroupItems($iUserId, $oAddressBook)
	{
		if ($this->init($iUserId))
		{
			$aItems = $this->getObjectItems($iUserId, $oAddressBook);

			foreach ($aItems as $oItem)
			{
				$sItemId = $oItem->getName();
				$oVCard = false;
				try
				{
					$oVCard = \Sabre\VObject\Reader::read($oItem->get());
				}
				catch(\Exception $ex)
				{
					\Aurora\System\Api::Log('SABREDAV: Invalid VCard with Id='.$sItemId);
				}
				if ($oVCard)
				{
					if (isset($oVCard->CATEGORIES))
					{
						$aCategories = $oVCard->CATEGORIES->getParts();
						foreach($aCategories as $sCategory)
						{
							$sCategory = trim($sCategory);
							if (!empty($sCategory))
							{
								$this->aGroupItemsCache[$oAddressBook->getName()][$sCategory][$sItemId] = $sItemId;
							}
						}
					}
				}
				unset($oVCard);
			}
		}
	}

	public function ___qSortCallback($a, $b)
	{
		$sSortField = $GLOBALS['ItemsSortField'];
		$iSortOrder = $GLOBALS['ItemsSortOrder'];

		if ($a->{$sSortField} === $b->{$sSortField})
		{
			return 0;
		}
		else if (\Aurora\System\Enums\SortOrder::ASC == $iSortOrder)
		{
			return ($a->{$sSortField} > $b->{$sSortField}) ? -1 : 1;
		}
		else
		{
			return ($a->{$sSortField} < $b->{$sSortField}) ? -1 : 1;
		}
	}


	/**
	 * @param array $aItems
	 * @param int $iSortField
	 * @param int $iSortOrder
	 */
	protected function sortItems(&$aItems, $iSortField, $iSortOrder)
	{
		$aMapSortField = array(
			\Aurora\Modules\Contacts\Enums\SortField::Email => 'Email',
			\Aurora\Modules\Contacts\Enums\SortField::Name => 'Name',
			\Aurora\Modules\Contacts\Enums\SortField::Frequency => 'Frequency'
		);

		if (!isset($aMapSortField[$iSortField]))
		{
			return;
		}

		$GLOBALS['ItemsSortField'] = $aMapSortField[$iSortField];
		$GLOBALS['ItemsSortOrder'] = $iSortOrder;

		// Sort
		usort($aItems, array(&$this, '___qSortCallback'));

		unset($GLOBALS['ItemsSortField']);
		unset($GLOBALS['ItemsSortOrder']);
	}

	/**
	 * @param int $iUserId
	 * @param int $iOffset
	 * @param int $iRequestLimit
	 * @return bool|array
	 */
	public function getContactItemsWithoutOrder($iUserId, $iOffset, $iRequestLimit)
	{
		$aResult = array();

		$oAddressBook = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
		$aContactItems = $this->getObjectItems($iUserId, $oAddressBook);

		foreach ($aContactItems as $oItem)
		{
			$sItemId = $oItem->getName();
			$oVCard = null;
			try
			{
				$oVCard = \Sabre\VObject\Reader::read($oItem->get());
			}
			catch(\Exception $ex)
			{
				\Aurora\System\Api::Log('SABREDAV: Invalid VCard with Id='.$sItemId);
			}
			if (isset($oVCard))
			{
				$oContactItem = new \Aurora\Modules\Contacts\Classes\ContactListItem();
				$oContactItem->InitBySabreCardDAVCard($oVCard);
				$oContactItem->Id = $oItem->getName();

				$aResult[] = $oContactItem;
				unset($oContactItem);
			}
			unset($oVCard);
		}

		if ($iOffset < 0 &&  $iRequestLimit < 0)
		{
			return $aResult;
		}
		else
		{
			return array_slice($aResult, $iOffset, $iRequestLimit);
		}
	}

	/**
	 * 
	 * @param int $iSortField
	 * @param int $iSortOrder
	 * @param int $iOffset
	 * @param int $iRequestLimit
	 * @param array $aFilters
	 * @param int $iIdGroup
	 * @return array
	 */
	public function getContactItems($iSortField, $iSortOrder, $iOffset, $iRequestLimit, $aFilters, $iIdGroup)
	{
		return array();
		
//		$oAddressBook = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
//		$aResult = $this->getItems($iUserId, $oAddressBook, $sSearch, $sFirstCharacter, $iGroupId);
//		$this->sortItems($aResult, $iSortField, $iSortOrder);
//
//		return array_slice($aResult, $iOffset, $iRequestLimit);
	}
	
	/**
	 * @param int $iUserId
	 * @return bool|array
	 */
	public function GetContactItemObjects($iUserId)
	{
		$oAddressBook = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
		return $this->getObjectItems($iUserId, $oAddressBook);
	}	

	/**
	 * @param int $iUserId
	 * @param string $sSearch
	 * @param string $sFirstCharacter
	 * @param int $iGroupId
	 * @param int $iTenantId
	 * @return int
	 */
	public function getContactItemsCount($iUserId, $sSearch, $sFirstCharacter, $iGroupId, $iTenantId = null, $bAll = false)
	{
		$iCount = 0;
		$oAddressBook = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
		if (false !== $oAddressBook)
		{
			$iCount = count($this->getItems($iUserId, $oAddressBook, $sSearch, $sFirstCharacter, $iGroupId));
		}
		return $iCount;
	}

	/**
	 * @param int $iUserId
	 * @param string $sSearch
	 * @param string $sFirstCharacter
	 * @param string $sContactId
	 * @return bool|array
	 */
	protected function getGroupItemsWithoutOrder($iUserId, $sSearch = '', $sFirstCharacter = '', $sContactId = '')
	{
		$aResult = array();
		$this->init($iUserId);

		if (!empty($sContactId))
		{
			$oContact = $this->getContactById($iUserId, $sContactId);
			if ($oContact)
			{
				foreach ($oContact->GroupIds as $sGroupId)
				{
					$oContactItem = new \Aurora\Modules\Contacts\Classes\ContactListItem();
					$oContactItem->Id = (string) $sGroupId;
					$oContactItem->Name = (string) $sGroupId;
					$oContactItem->IsGroup = true;

					if ($sSearch == '' || stripos($oContactItem->Name, $sSearch) !== false)
					{
						$aResult[] = $oContactItem;
					}
					unset($oContactItem);
				}
			}
		}
		else
		{
			$oAddressBook = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
			if (false !== $oAddressBook)
			{
				$sName = $oAddressBook->getName();
				if (!isset($this->aGroupItemsCache[$sName]))
				{
					$this->getItems($iUserId, $oAddressBook);
				}

				if (isset($this->aGroupItemsCache[$sName]))
				{
					$aItems = $this->aGroupItemsCache[$sName];
					foreach ($aItems as $sKey => $aIds)
					{
						$aContactIds = array();
						foreach($aIds as $sContactId)
						{
							$aContactIds[] = $sContactId;
						}
						$oContactItem = new \Aurora\Modules\Contacts\Classes\ContactListItem();
						$oContactItem->Id = $sKey;
						$oContactItem->Name = $sKey;
						$oContactItem->IsGroup = true;

						if (empty($sContactId) || !empty($sContactId) && in_array($sContactId, $aContactIds))
						{
							if ($sSearch == '' || stripos($oContactItem->Name, $sSearch) !== false)
							{
								$aResult[] = $oContactItem;
							}
						}
						unset($oContactItem);
					}
				}
			}
		}
		return $aResult;
	}

	protected function searchContactItemsByEmail($sUserId, $sEmail, $oAddressBook)
	{
		$aResult = array();

		$aContactItems = $this->getItems($sUserId, $oAddressBook, $sEmail);
		foreach($aContactItems as $oContactItem)
		{
			$aResult[] = $oContactItem->Id;
		}

		return $aResult;
	}

	/**
	 * @param int $iUserId
	 * @param int $iSortField
	 * @param int $iSortOrder
	 * @param int $iOffset
	 * @param int $iRequestLimit
	 * @param string $sSearch
	 * @param string $sFirstCharacter
	 * @param string $sContactId
	 * @return bool|array
	 */
	public function getGroupItems($iUserId, $iSortField, $iSortOrder, $iOffset, $iRequestLimit, $sSearch, $sFirstCharacter, $sContactId)
	{
		$aResult = $this->getGroupItemsWithoutOrder($iUserId, $sSearch, $sFirstCharacter, $sContactId);
		$this->sortItems($aResult, $iSortField, $iSortOrder);
		return array_slice($aResult, $iOffset, $iRequestLimit);
	}

	/**
	 * @param int $iUserId
	 * @param string $sSearch
	 * @param string $sFirstCharacter
	 * @return int
	 */
	public function getGroupItemsCount($iUserId, $sSearch, $sFirstCharacter)
	{
		return count($this->getGroupItemsWithoutOrder($iUserId, $sSearch, $sFirstCharacter));
	}

	/**
	 * @param int $iUserId
	 * @param string $sSearch
	 * @param int $iRequestLimit
	 * @param bool $bPhoneOnly = false
	 * @return bool|array
	 */
	public function GetSuggestContactItems($iUserId, $sSearch, $iRequestLimit, $bPhoneOnly = false)
	{
		$aResult = array();
		$this->init($iUserId);

		$oDefaultAB = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
		$oCollectedAB = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME);

		$aCollectedContactItems = $this->getObjectItems($iUserId, $oCollectedAB);
		$aDefaultContactItems = $this->getObjectItems($iUserId, $oDefaultAB);

		$aContactItems = array_merge($aDefaultContactItems, $aCollectedContactItems);

		foreach ($aContactItems as $oItem)
		{
			$sItemId = $oItem->getName();
			$oVCard = null;
			try
			{
				$oVCard = \Sabre\VObject\Reader::read($oItem->get());
			}
			catch(\Exception $ex)
			{
				\Aurora\System\Api::Log('SABREDAV: Invalid VCard with Id='.$sItemId);
			}
			if (isset($oVCard))
			{
				$oContactItem = new \Aurora\Modules\Contacts\Classes\ContactListItem();
				$oContactItem->InitBySabreCardDAVCard($oVCard);
				$oContactItem->Id = $oItem->getName();

				if (empty($sSearch) ||
					stripos($oContactItem->Name, $sSearch) !== false ||
					stripos($oContactItem->Email, $sSearch) !== false)
				{
					$aResult[] = $oContactItem;
				}
				unset($oContactItem);
			}

			unset($oVCard);
		}

		$this->sortItems($aResult, \Aurora\Modules\Contacts\Enums\SortField::Frequency, \Aurora\System\Enums\SortOrder::ASC);

		return array_slice($aResult, 0, $iRequestLimit);
	}

	/**
	 * @param \Aurora\Modules\Contacts\Classes\Contact $oContact
	 * @param int $iUserId
	 * @return string
	 */
	public function updateContactUserId($oContact, $iUserId)
	{
		$bResult = false;
		
		$sAddressBook = $oContact->Storage === 'shared' ? \Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME : \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME;
		$oAddressBookFrom = $this->getAddressBook($oContact->IdUser, $sAddressBook);
		$oContactItem = $this->geItem($oContact->IdUser, $oAddressBookFrom, $oContact->UUID . '.vcf');
		if ($oContactItem)
		{
			$oAddressBookTo = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
			if ($oAddressBookTo)
			{
				try
				{
					$sData = $oContactItem->get();
					$oContactItem->delete();
					$oAddressBookTo->createFile($oContact->UUID . '.vcf', $sData);
					$bResult = true;
				}
				catch (\Exception $ex)
				{
					\Aurora\System\Api::Log($ex->getTraceAsString());
					$bResult = false;
				}
			}
		}
		return $bResult;
	}
	
	/**
	 * @param \Aurora\Modules\Contacts\Classes\Contact $oContact
	 * @return bool
	 */
	public function updateContact($oContact)
	{
		$bResult = false;
		$iUserId = $oContact->IdUser;
		$oAddressBook = null;
		if ($oContact->Storage === 'personal')
		{
			$oAddressBook = $this->getAddressBook($oContact->IdUser, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
		}
		else if ($oContact->Storage === 'shared')
		{
			$oAddressBook = $this->getAddressBook($oContact->IdUser, \Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME);
		}
		else if ($oContact->Storage === 'team')
		{
			$bResult = true;
		}
		else if ($oContact->Storage === 'addressbook')
		{
			$oEavAddressBook = AddressBook::where('Id', $oContact->AddressBookId)
				->where('UserId', $oContact->IdUser)->first();

			if ($oEavAddressBook)
			{
				$oAddressBook = $this->getAddressBook($oContact->IdUser, $oEavAddressBook->UUID);
			}
		}

		$oContactItem = $oAddressBook ? $this->geItem($iUserId, $oAddressBook, $oContact->{'DavContacts::UID'} . '.vcf') : null;
		if ($oContactItem)
		{
			$sData = $oContactItem->get();
/*
			$sETag = md5($sData);
			if ($oContact->ETag !== $sETag)
			{
 				throw new \Aurora\System\Exceptions\BaseException(Errs::Sabre_PreconditionFailed);
			}
 */

			$oVCard = \Sabre\VObject\Reader::read($sData);
			if ($oVCard)
			{
				\Aurora\Modules\Contacts\Classes\VCard\Helper::UpdateVCardFromContact($oContact, $oVCard);
				$oContactItem->put($oVCard->serialize());
				$bResult = true;
			}
			unset($oVCard);
		}

		return $bResult;
	}
	
	/**
	 * @param int $iUserId
	 * @param string $sUID
	 * @param string $sFromAddressBook
	 * @param string $sToAddressBook
	 * @return bool
	 */
	public function copyContact($iUserId, $sUID, $sFromAddressBook, $sToAddressBook)
	{
		$oFromAddressBook = $this->getAddressBook($iUserId, $sFromAddressBook);
		$oToAddressBook = $this->getAddressBook($iUserId, $sToAddressBook);
		
		$sId = $sUID . '.vcf';
		if ($oFromAddressBook->childExists($sUID . '.vcf'))
		{
			$oCard = $oFromAddressBook->getChild($sId);
			if ($oCard)
			{
				$oToAddressBook->createFile($sId, $oCard->get());
				$oCard->delete();
			}
		}
		
		return true;
	}	

	/**
	 * @param \Aurora\Modules\Contacts\Classes\Group $oGroup
	 * @return bool
	 */
	public function updateGroup($oGroup)
	{
		$bResult = false;

		$oAddressBook = $this->getAddressBook($oGroup->IdUser, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
		$oGroupItem = $oAddressBook ? $this->geItem($oGroup->IdUser, $oAddressBook, $oGroup->{'DavContacts::UID'} . '.vcf') : null;
		if ($oGroupItem)
		{
			$sData = $oGroupItem->get();

			$oVCard = \Sabre\VObject\Reader::read($sData);
			if ($oVCard)
			{
				\Aurora\Modules\Contacts\Classes\VCard\Helper::UpdateVCardFromGroup($oGroup, $oVCard);
				unset($oVCard->MEMBER);
				unset($oVCard->{'X-ADDRESSBOOKSERVER-MEMBER'});
				foreach ($oGroup->Contacts as $oGroupContact)
				{
					$oContact = \Aurora\Modules\Contacts\Models\Contact::where('UUID', $oGroupContact->UUID)->first();
					$sVCardUID = null;
					if ($oContact->Storage !== 'team')
					{
						if (!empty($oContact->{'DavContacts::VCardUID'}))
						{
							$sVCardUID = $oContact->{'DavContacts::VCardUID'};
						}
						else
						{
							$sVCardUID = $this->fixContactVCardUid($oAddressBook, $oContact);
						}
					}
					else
					{
						$sVCardUID = $oContact->UUID;
					}
					if (isset($sVCardUID))
					{
						$oVCard->add('X-ADDRESSBOOKSERVER-MEMBER', 'urn:uuid:' . $sVCardUID);
					}
				}		
		
				$oGroupItem->put($oVCard->serialize());
				$bResult = true;
			}
			unset($oVCard);
		}

		return $bResult;
	}

	/**
	 * @param \Aurora\Modules\Contacts\Classes\Contact $oContact
	 * @return bool
	 */
	public function createContact($oContact)
	{
		$bResult = false;
		if (isset($oContact))
		{
			$this->init($oContact->IdUser);
			$oAddressBook = null;
			if ($oContact->Storage === 'personal')
			{
				if (!$oContact->Auto)
				{
					$oAddressBook = $this->getAddressBook($oContact->IdUser, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
				}
				else
				{
					$oAddressBook = $this->getAddressBook($oContact->IdUser, \Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME);
				}
			}
			else if ($oContact->Storage === 'shared')
			{
				$oAddressBook = $this->getAddressBook($oContact->IdUser, \Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME);
			}
			else if ($oContact->Storage === 'addressbook')
			{
				$oEavAddressBook = AddressBook::where('Id', $oContact->AddressBookId)
					->where('UserId', $oContact->IdUser)->first();

				if ($oEavAddressBook)
				{
					$oAddressBook = $this->getAddressBook($oContact->IdUser, $oEavAddressBook->UUID);
				}
			}
			if ($oAddressBook)
			{
				$oContact->{'DavContacts::UID'} .= '.vcf';

				$oVCard = new \Sabre\VObject\Component\VCard();
				\Aurora\Modules\Contacts\Classes\VCard\Helper::UpdateVCardFromContact($oContact, $oVCard);
				$oAddressBook->createFile($oContact->{'DavContacts::UID'}, $oVCard->serialize());
				$bResult = true;
			}
/*
 *  for collected addressbook
 * 
 * 
			$oAddressBook = $this->getAddressBook($oContact->IdUser, \Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME);
			$aContactIds = $this->searchContactItemsByEmail($oContact->IdUser, $oContact->ViewEmail, $oAddressBook);

			$this->deleteContactsByAddressBook($oContact->IdUser, $aContactIds, $oAddressBook);
 * 
 */
		}

		return $bResult;
	}

	protected function fixContactVCardUid($oAddressBook, $oContact)
	{
		$mResult = null;
		if ($oAddressBook->childExists($oContact->{'DavContacts::UID'} . '.vcf'))
		{
			$oChild = $oAddressBook->getChild($oContact->{'DavContacts::UID'} . '.vcf');
			if ($oChild)
			{
				$oVCard = \Sabre\VObject\Reader::read($oChild->get());
				$sVCardUID = $oVCard->UID;
				$oContact->setExtendedProp('DavContacts::VCardUID', $sVCardUID);
				$oContact->save();

				$mResult = $sVCardUID; 
			}
		}
		else
		{
			$mResult = $oContact->UUID;
		}

		return $mResult;
	}

	/**
	 * @param \Aurora\Modules\Contacts\Classes\Group $oGroup
	 * @return bool
	 */
	public function createGroup($oGroup)
	{
		$oVCard = new \Sabre\VObject\Component\VCard();
		\Aurora\Modules\Contacts\Classes\VCard\Helper::UpdateVCardFromGroup($oGroup, $oVCard);
		
		unset($oVCard->{'X-ADDRESSBOOKSERVER-MEMBER'});
		$oAddressBook = $this->getAddressBook($oGroup->IdUser, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
		foreach ($oGroup->Contacts as $oGroupContact)
		{
			$oContact = \Aurora\Modules\Contacts\Models\Contact::where('UUID', $oGroupContact->UUID)->first();
			if ($oContact)
			{
				$sVCardUID = null;
				if ($oContact->Storage !== 'team')
				{
					if (!empty($oContact->{'DavContacts::VCardUID'}))
					{
						$sVCardUID = $oContact->{'DavContacts::VCardUID'};
					}
					else
					{
						$sVCardUID = $this->fixContactVCardUid($oAddressBook, $oContact);
					}
				}
				else
				{
					$sVCardUID = $oContact->UUID;
				}
				if (isset($sVCardUID))
				{
					$oVCard->add('X-ADDRESSBOOKSERVER-MEMBER', 'urn:uuid:' . $sVCardUID);
				}
			}
		}		

		$oAddressBook = $this->getAddressBook($oGroup->IdUser, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
		$oAddressBook->createFile($oGroup->{'DavContacts::UID'} . '.vcf', $oVCard->serialize());


		return true; //$this->updateGroup($oGroup);
	}

	/**
	 * @param int $iUserId
	 * @param array $aContactIds
	 * @param \Afterlogic\DAV\CardDAV\AddressBook
	 * @return bool
	 */
	protected function deleteContactsByAddressBook($iUserId, $aContactIds, $oAddressBook)
	{
		$this->init($iUserId);

		if ($oAddressBook)
		{
			foreach($aContactIds as $sContactId)
			{
				if (!strripos($sContactId, '.vcf'))
				{
					$sContactId .= '.vcf';
				}
				if ($oAddressBook->childExists($sContactId))
				{
					$oContact = $oAddressBook->GetChild($sContactId);
					$oContact->delete();
				}
			}
			return true;
		}
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param array $aContactIds
	 * @return bool
	 */
	public function deleteContacts($iUserId, $aContactIds, $sAddressBook = \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME)
	{
		$this->init($iUserId);
		$oAddressBook = $this->getAddressBook($iUserId, $sAddressBook);
		return $this->deleteContactsByAddressBook($iUserId, $aContactIds, $oAddressBook);
	}

	/**
	 * @param int $iUserId
	 * @param array $aContactIds
	 * @return bool
	 */
	public function deleteSuggestContacts($iUserId, $aContactIds)
	{
		$this->init($iUserId);
		$oAddressBook = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME);
		return $this->deleteContactsByAddressBook($iUserId, $aContactIds, $oAddressBook);
	}
	
	/**
	 * @param int $iUserId
	 * @param string $aGroupIds
	 * @return bool
	 */
	public function deleteGroup($iUserId, $iGroupId)
	{
		$this->init($iUserId);

		$oAddressBook = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
		if ($oAddressBook)
		{
			if ($oAddressBook->childExists($iGroupId . '.vcf'))
			{
				$oChild = $oAddressBook->getChild($iGroupId . '.vcf');
				$oChild->delete();
				return true;
			}

		}
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param array $aEmails
	 * @return bool
	 */
	public function updateSuggestTable($iUserId, $aEmails)
	{
		$bResult = false;
		$this->init($iUserId);

		$oDefautltAB = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
		$oCollectedAB = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME);
		
		$aCollectedContactItems = $this->getObjectItems($iUserId, $oCollectedAB);

		foreach ($aEmails as $sEmail => $sName)
		{
			$mFindContact = false;
			if ($aCollectedContactItems)
			{
				foreach ($aCollectedContactItems as $oCollectedContactItem)
				{
					$oVCard = \Sabre\VObject\Reader::read($oCollectedContactItem->get());
					if (isset($oVCard->EMAIL))
					{
						foreach ($oVCard->EMAIL as $oEmail)
						{
							if (strtolower((string)$oEmail) == strtolower($sEmail))
							{
								$mFindContact = $oCollectedContactItem;
								break;
							}
						}
					}
					unset($oVCard);
				}
			}

			$aDefaultContactIds = $this->searchContactItemsByEmail($iUserId, $sEmail, $oDefautltAB);
			if (count($aDefaultContactIds) === 0)
			{
				if ($mFindContact === false)
				{
					$sUUID = \Sabre\DAV\UUIDUtil::getUUID();
					$oContact = new \Aurora\Modules\Contacts\Models\Contact();
					$oContact->FullName = $sName;
					$oContact->PersonalEmail = $sEmail;
					$oContact->setExtendedProp('DavContacts::UID', $sUUID);

					$oVCard = new \Sabre\VObject\Component\VCard();
					$oVCard->{'X-AFTERLOGIC-USE-FREQUENCY'} = '1';
					\Aurora\Modules\Contacts\Classes\VCard\Helper::UpdateVCardFromContact($oContact, $oVCard);

					$oCollectedAB->createFile($sUUID . '.vcf', $oVCard->serialize());
					$bResult = true;
				}
				else if ($mFindContact instanceof \Sabre\CardDAV\Card)
				{
					$oVCard = \Sabre\VObject\Reader::read($mFindContact->get());
					if (isset($oVCard->{'X-AFTERLOGIC-USE-FREQUENCY'}))
					{
						$oVCard->{'X-AFTERLOGIC-USE-FREQUENCY'} = (int)$oVCard->{'X-AFTERLOGIC-USE-FREQUENCY'}->getValue() + 1;
					}
					else
					{
						$oVCard->{'X-AFTERLOGIC-USE-FREQUENCY'} = '1';
					}
					$mFindContact->put($oVCard->serialize());
					unset($oVCard);
				}
			}
			else
			{
				foreach($aDefaultContactIds as $sDefaultContactId)
				{
					$mDefaultContact = $this->geItem($iUserId, $oDefautltAB, $sDefaultContactId);
					if ($mDefaultContact !== false)
					{
						$oVCard = \Sabre\VObject\Reader::read($mDefaultContact->get());
						if (isset($oVCard->{'X-AFTERLOGIC-USE-FREQUENCY'}))
						{
							$oVCard->{'X-AFTERLOGIC-USE-FREQUENCY'} = (int)$oVCard->{'X-AFTERLOGIC-USE-FREQUENCY'}->getValue() + 1;
						}
						else
						{
							$oVCard->{'X-AFTERLOGIC-USE-FREQUENCY'} = '1';
						}
						$mDefaultContact->put($oVCard->serialize());
						unset($oVCard);
					}
				}

				if ($mFindContact instanceof \Sabre\CardDAV\Card)
				{
					$mFindContact->delete();
				}
			}
		}

		return $bResult;
	}

	/**
	 * @param int $iUserId
	 * @param array $aContactIds
	 * @return bool
	 */
//	public function DeleteContactsExceptIds($iUserId, $aContactIds)
//	{
//		$this->init($iUserId);
//
//		$oAddressBook = $this->getAddressBook($iUserId, \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME);
//		if ($oAddressBook)
//		{
//			$aContactItems = $this->getObjectItems($iUserId);
//			foreach ($aContactItems as $oContactItem)
//			{
//				$vCard = \Sabre\VObject\Reader::read($oContactItem->get());
//				if (isset($vCard->UID) && !in_array((string)$vCard->UID, $aContactIds))
//				{
//					$oContactItem->delete();
//				}
//			}
//			return true;
//		}
//		return false;
//	}

	/**
	 * @param int $iUserId
	 * @param array $aGroupIds
	 * @return bool
	 */
//	public function DeleteGroupsExceptIds($iUserId, $aGroupIds)
//	{
//		return true;
//	}

	/**
	 * @return bool
	 */
	public function flushContacts()
	{
		return true;

	}

	/**
	 * @param int $iUserId
	 * @return bool
	 */
	public function clearAllContactsAndGroups($iUserId)
	{
		$bResult = false;
		$this->init($iUserId);

		$oAddressBooks = new \Afterlogic\DAV\CardDAV\AddressBookRoot(
			\Afterlogic\DAV\Backend::Carddav(), $this->Principal);

		foreach ($oAddressBooks->getChildren() as $oAddressBook)
		{
			if ($oAddressBook && $oAddressBook instanceof \Sabre\CardDAV\AddressBook)
			{
				try
				{
					$oAddressBook->delete();
					$bResult = true;
				}
				catch (\Exception $ex)
				{
					\Aurora\System\Api::Log($ex->getTraceAsString());
					$bResult = false;
				}
			}
		}
		return $bResult;
	}

	/**
	 * @param \Aurora\Modules\Contacts\Classes\Group $oGroup
	 * @param array $aContactIds
	 * @return bool
	 */
	public function addContactsToGroup($oGroup, $aContactIds)
	{
		return $this->updateGroup($oGroup);
	}

	/**
	 * @param \Aurora\Modules\Contacts\Classes\Group $oGroup
	 * @param array $aContactIds
	 * @return bool
	 */
	public function removeContactsFromGroup($oGroup, $aContactIds)
	{
		$bResult = true;
		
		if ($oGroup && is_array($aContactIds))
		{
			foreach ($aContactIds as $sContactId)
			{
				$oContact = $this->getContactById($oGroup->IdUser, $sContactId);
				if ($oContact)
				{
					$aGroupIds = $oContact->GroupIds;
					$oContact->GroupIds = array_diff($aGroupIds, array($oGroup->Name));
					$bResult = $this->updateContact($oContact);
				}
			}
		}
		else
		{
			$bResult = false;
		}
		
		return (bool) $bResult;
	}
}
