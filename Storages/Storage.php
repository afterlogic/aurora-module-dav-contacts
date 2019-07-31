<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\DavContacts\Storages;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Storage extends \Aurora\System\Managers\AbstractStorage
{
	/**
	 * @param int $iUserId
	 * @param mixed $mContactId
	 * @return \Aurora\Modules\Contacts\Classes\Contact | false
	 */
	public function getContactById($iUserId, $mContactId)
	{
		return false;
	}

	/**
	 * @param int $iUserId
	 * @return \Aurora\Modules\Contacts\Classes\Contact|null
	 */
	public function GetMyGlobalContact($iUserId)
	{
		return null;
	}

	/**
	 * @param mixed $mTypeId
	 * @param int $iContactType
	 * @return \Aurora\Modules\Contacts\Classes\Contact|bool
	 */
	public function GetContactByTypeId($mTypeId, $mContactId)
	{
		return false;
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
	 * @return \Aurora\Modules\Contacts\Classes\Contact|bool
	 */
	public function getContactByStrId($iUserId, $sContactStrId)
	{
		return false;
	}
	
	/**
	 * @param int $iUserId
	 * @return array|bool
	 */
	public function getSharedContactIds($iUserId, $sContactStrId)
	{
		return array();
	}
	

	/**
	 * @param \Aurora\Modules\Contacts\Classes\Contact $oContact
	 * @return array|bool
	 */
	public function getContactGroupIds($oContact)
	{
		return array();
	}

	/**
	 * @param int $iUserId
	 * @param mixed $mGroupId
	 * @return \Aurora\Modules\Contacts\Classes\Group
	 */
	public function getGroupById($iUserId, $mGroupId)
	{
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param string $sGroupStrId
	 * @return \Aurora\Modules\Contacts\Classes\Group
	 */
	public function getGroupByStrId($iUserId, $sGroupStrId)
	{
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param string $sName
	 * @return \Aurora\Modules\Contacts\Classes\Group
	 */
	public function getGroupByName($iUserId, $sName)
	{
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param int $iOffset
	 * @param int $iRequestLimit
	 * @return bool|array
	 */
	public function getContactItemsWithoutOrder($iUserId, $iOffset, $iRequestLimit)
	{
		return array();
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
		return 0;
	}

	/**
	 * @param int $iUserId
	 * @param int $iSortField
	 * @param int $iSortOrder
	 * @param int $iOffset
	 * @param int $iRequestLimit
	 * @param string $sSearch
	 * @param string $sFirstCharacter
	 * @param int $iContactId
	 * @return bool|array
	 */
	public function getGroupItems($iUserId, $iSortField, $iSortOrder, $iOffset, $iRequestLimit, $sSearch, $sFirstCharacter, $iContactId)
	{
		return array();
	}

	/**
	 * @param int $iUserId
	 * @param string $sSearch
	 * @param string $sFirstCharacter
	 * @return int
	 */
	public function getGroupItemsCount($iUserId, $sSearch, $sFirstCharacter)
	{
		return 0;
	}

	/**
	 * @param int $iUserId
	 * @param int $iTenantId = 0
	 * @param bool $bAddGlobal = true
	 * @return bool|array
	 */
	public function GetAllContactsNamesWithPhones($iUserId, $iTenantId = 0, $bAddGlobal = true)
	{
		return array();
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
		return array();
	}

	/**
	 * @param int $iUserId
	 * @param string $sSearch
	 * @param int $iRequestLimit
	 * @return bool|array
	 */
	public function GetSuggestGroupItems($iUserId, $sSearch, $iRequestLimit)
	{
		return array();
	}
	
	/**
	 * @param \Aurora\Modules\Contacts\Classes\Contact $oContact
	 * @return bool
	 */
	public function updateContact($oContact)
	{
		return false;
	}
	
	/**
	 * @param \Aurora\Modules\Contacts\Classes\Contact $oContact
	 * @param int $iUserId
	 * @return string
	 */
	public function updateContactUserId($oContact, $iUserId)
	{
		return true;
	}

	/**
	 * @param \Aurora\Modules\Contacts\Classes\Group $oGroup
	 * @return bool
	 */
	public function updateGroup($oGroup)
	{
		return false;
	}

	/**
	 * @param \Aurora\Modules\Contacts\Classes\Contact $oContact
	 * @return bool
	 */
	public function createContact($oContact)
	{
		return false;
	}

	/**
	 * @param \Aurora\Modules\Contacts\Classes\Group $oGroup
	 * @return bool
	 */
	public function createGroup($oGroup)
	{
		return false;
	}

	/**
	 * @param int $iUserId
	 * @param array $aContactIds
	 * @return bool
	 */
	public function deleteContacts($iUserId, $aContactIds, $sAddressBook = \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME)
	{
		return true;
	}
	
	/**
	 * @param int $iUserId
	 * @param array $aContactIds
	 * @return bool
	 */
	public function deleteSuggestContacts($iUserId, $aContactIds)
	{
		return true;
	}	

	/**
	 * @param int $iUserId
	 * @param string $iGroupId
	 * @return bool
	 */
	public function deleteGroup($iUserId, $iGroupId)
	{
		return true;
	}

	/**
	 * @param int $iUserId
	 * @param string $sEmail
	 * @return bool
	 */
	public function updateSuggestTable($iUserId, $aEmails)
	{
		return true;
	}

	/**
	 * @param int $iUserId
	 * @param array $aContactIds
	 * @return bool
	 */
//	public function DeleteContactsExceptIds($iUserId, $aContactIds)
//	{
//		return true;
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
	 * @param \Aurora\Modules\StandardAuth\Classes\Account $oAccount
	 * @return bool
	 */
	public function clearAllContactsAndGroups($oAccount)
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function flushContacts()
	{
		return true;

	}

	/**
	 * @param \Aurora\Modules\Contacts\Classes\Group $oGroup
	 * @param array $aContactIds
	 * @return bool
	 */
	public function addContactsToGroup($oGroup, $aContactIds)
	{
		return true;
	}

	/**
	 * @param int $iUserId
	 * @param mixed $mContactId
	 * @return \Aurora\Modules\Contacts\Classes\Contact | false
	 */
	public function GetGlobalContactById($iUserId, $mContactId)
	{
		return false;
	}	
	
}
