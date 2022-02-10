<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\DavContacts;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Manager extends \Aurora\System\Managers\AbstractManagerWithStorage
{
	/**
	 * Creates a new instance of the object.
	 *
	 * @param \Aurora\System\Module\AbstractModule $oModule
	 */
	public function __construct(\Aurora\System\Module\AbstractModule $oModule = null)
	{
		parent::__construct($oModule, new Storages\Sabredav\Storage($this));
	}

	/**
     * Returns contact item identified by user ID and contact ID.
     *
	 * @param int $iUserId
	 * @param mixed $mContactId
     *
	 * @return \Aurora\Modules\Contacts\Classes\Contact|bool
	 */
	public function getContactById($iUserId, $mContactId, $sAddressBookName = \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME)
	{
		$oContact = null;
		try
		{
			$oContact = $this->oStorage->getContactById($iUserId, $mContactId, $sAddressBookName);
			if ($oContact)
			{
				$mGroupIds = $this->getContactGroupIds($oContact);
				if (is_array($mGroupIds))
				{
					$oContact->GroupIds = $mGroupIds;
				}
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$oContact = false;
			$this->setLastException($oException);
		}
		
		return $oContact;
	}

	/**
     * //TODO
     *
	 * @param mixed $mTypeId
	 * @param int $iContactType
     *
	 * @return \Aurora\Modules\Contacts\Classes\Contact|bool
	 */
	public function GetContactByTypeId($mTypeId, $iContactType)
	{
		$oContact = null;
		try
		{
			$oContact = $this->oStorage->GetContactByTypeId($mTypeId, $iContactType);
			if ($oContact)
			{
				$mGroupIds = $this->getContactGroupIds($oContact);
				if (is_array($mGroupIds))
				{
					$oContact->GroupIds = $mGroupIds;
				}
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$oContact = false;
			$this->setLastException($oException);
		}

		return $oContact;
	}

	/**
     * Returns contact item identified by email address.
     *
	 * @param int $iUserId
	 * @param string $sEmail
     *
	 * @return \Aurora\Modules\Contacts\Classes\Contact|bool
	 */
	public function getContactByEmail($iUserId, $sEmail)
	{
		$oContact = null;
		try
		{
			$oContact = $this->oStorage->getContactByEmail($iUserId, $sEmail);
			if ($oContact)
			{
				$mGroupIds = $this->getContactGroupIds($oContact);
				if (is_array($mGroupIds))
				{
					$oContact->GroupIds = $mGroupIds;
				}
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$oContact = false;
			$this->setLastException($oException);
		}

		return $oContact;
	}

	/**
     * Returns contact item identified by str_id value.
     *
	 * @param int $iUserId
	 * @param string $sContactStrId
	 * @param int $iSharedTenantId. Default value is **null**
     *
	 * @return \Aurora\Modules\Contacts\Classes\Contact
	 */
	public function getContactByStrId($iUserId, $sContactStrId, $iSharedTenantId = null)
	{
		$oContact = null;
		try
		{
			$oContact = $this->oStorage->getContactByStrId($iUserId, $sContactStrId, $iSharedTenantId);
			if ($oContact)
			{
				$mGroupIds = $this->getContactGroupIds($oContact);
				if (is_array($mGroupIds))
				{
					$oContact->GroupIds = $mGroupIds;
				}
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$oContact = false;
			$this->setLastException($oException);
		}

		return $oContact;
	}
	
	/**
     * Returns contact item identified by user ID and contact ID.
     *
	 * @param int $iUserId
	 * @param mixed $mContactId
     *
	 * @return resource | bool
	 */
	public function getVCardObjectById($iUserId, $mContactId, $sAddressBookName = \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME)
	{
		$mResult = null;
		try
		{
			$oVCardObject = $this->oStorage->getVCardObjectById($iUserId, $mContactId, $sAddressBookName);
			if ($oVCardObject)
			{
				$mResult = $oVCardObject->get();
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$mResult = false;
			$this->setLastException($oException);
		}
		
		return $mResult;
	}	
	
	/**
     * Returns list of shared contacts by str_id value.
     *
	 * @param int $iUserId
	 * @param int $iSharedTenantId Default value is **null**
     *
	 * @return array|bool
	 */
	public function getSharedContactIds($iUserId, $iSharedTenantId = null)
	{
		$aContactIds = array();
		try
		{
			$aContactIds = $this->oStorage->getSharedContactIds($iUserId, $iSharedTenantId);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$aContactIds = false;
			$this->setLastException($oException);
		}

		return $aContactIds;
	}
	
	/**
	 * @param \Aurora\Modules\Contacts\Classes\Contact $oContact
	 *
	 * @return array|bool
	 */
	public function getContactGroupIds($oContact)
	{
		$aGroupIds = false;
		try
		{
			$aGroupIds = $this->oStorage->getContactGroupIds($oContact);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$aGroupIds = false;
			$this->setLastException($oException);
		}

		return $aGroupIds;
	}

	/**
	 * @param int $iUserId
	 * @param mixed $mGroupId
	 *
	 * @return \Aurora\Modules\Contacts\Classes\Group
	 */
	public function getGroupById($iUserId, $mGroupId)
	{
		$oGroup = null;
		try
		{
			$oGroup = $this->oStorage->getGroupById($iUserId, $mGroupId);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$oGroup = false;
			$this->setLastException($oException);
		}

		return $oGroup;
	}

	/**
	 * @param int $iUserId
	 * @param string $sGroupStrId
	 *
	 * @return \Aurora\Modules\Contacts\Classes\Group
	 */
	public function getGroupByStrId($iUserId, $sGroupStrId)
	{
		$oGroup = null;
		try
		{
			$oGroup = $this->oStorage->getGroupByStrId($iUserId, $sGroupStrId);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$oGroup = false;
			$this->setLastException($oException);
		}

		return $oGroup;
	}

	/**
	 * @param int $iUserId
	 * @param string $sName
	 *
	 * @return \Aurora\Modules\Contacts\Classes\Group
	 */
	public function getGroupByName($iUserId, $sName)
	{
		$oGroup = null;
		try
		{
			$oGroup = $this->oStorage->getGroupByName($iUserId, $sName);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$oGroup = false;
			$this->setLastException($oException);
		}

		return $oGroup;
	}

	/**
	 * @param \Aurora\Modules\Contacts\Classes\Contact $oContact
	 *
	 * @return bool
	 */
	public function updateContact($oContact)
	{
		$bResult = false;
		try
		{
			if ($oContact->validate())
			{
				$bResult = $this->oStorage->updateContact($oContact);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}

//		TODO: a.ovcharov@gmail.com
//		if ($bResult)
//		{
//			$oApiVoiceManager = /* @var $oApiVoiceManager \CApiVoiceManager */\Aurora\System\Api::Manager('voice');
//			if ($oApiVoiceManager)
//			{
//				$oApiVoiceManager->flushCallersNumbersCache($oContact->IdUser);
//			}
//		}

		return $bResult;
	}
	
	/**
	 * @param \Aurora\Modules\Contacts\Classes\Contact $oContact
	 * @param int $iUserId
	 *
	 * @return string
	 */
	public function updateContactUserId($oContact, $iUserId)
	{
		$bResult = false;
		try
		{
			if ($oContact->validate())
			{
				$bResult = $this->oStorage->updateContactUserId($oContact, $iUserId);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}

		if ($bResult)
		{
			$oApiVoiceManager = /* @var $oApiVoiceManager \CApiVoiceManager */\Aurora\System\Api::Manager('voice');
			if ($oApiVoiceManager)
			{
				$oApiVoiceManager->flushCallersNumbersCache($iUserId);
			}
		}

		return $bResult;
	}	

	/**
	 * @param \Aurora\Modules\Contacts\Classes\Group $oGroup
	 * @return bool
	 */
	public function updateGroup($oGroup)
	{
		$bResult = false;
		try
		{
			if ($oGroup->validate())
			{
				$bResult = $this->oStorage->updateGroup($oGroup);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}

		return $bResult;
	}

	/**
	 * @param int $iUserId
	 * @param string $sSearch Default value is empty string
	 * @param string $sFirstCharacter Default value is empty string
	 * @param int $iGroupId Default value is **0**
	 * @param int $iTenantId Default value is **null**
	 * @param bool $bAll Default value is **false**
	 *
	 * @return int
	 */
	public function getContactItemsCount($iUserId, $sSearch = '', $sFirstCharacter = '', $iGroupId = 0, $iTenantId = null, $bAll = false)
	{
		$iResult = 0;
		try
		{
			$iResult = $this->oStorage->getContactItemsCount($iUserId, $sSearch, $sFirstCharacter, $iGroupId, $iTenantId, $bAll);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$iResult = 0;
			$this->setLastException($oException);
		}

		return $iResult;
	}

	/**
	 * @param int $iUserId
	 * @param int $iOffset Default value is **0**
	 * @param int $iRequestLimit Default value is **20**
	 *
	 * @return bool|array
	 */
	public function getContactItemsWithoutOrder($iUserId, $iOffset = 0, $iRequestLimit = 20)
	{
		$mResult = false;
		try
		{
			$mResult = $this->oStorage->getContactItemsWithoutOrder($iUserId, $iOffset, $iRequestLimit);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$mResult = false;
			$this->setLastException($oException);
		}

		return $mResult;
	}

	/**
	 * 
	 * @param int $iSortField
	 * @param int $iSortOrder
	 * @param int $iOffset
	 * @param int $iRequestLimit
	 * @param array $aFilters
	 * @param int $iIdGroup
	 * @return boolean
	 */
	public function getContactItems($iSortField = \Aurora\Modules\Contacts\Enums\SortField::Email, $iSortOrder = \Aurora\System\Enums\SortOrder::ASC,
		$iOffset = 0, $iRequestLimit = 20, $aFilters = array(), $iIdGroup = 0)
	{
		$mResult = false;
		try
		{
			$mResult = $this->oStorage->getContactItems($iSortField, $iSortOrder, $iOffset, $iRequestLimit, $aFilters, $iIdGroup);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$mResult = false;
			$this->setLastException($oException);
		}

		return $mResult;
	}
	
	/**
	 * @param string $mUserId
	 *
	 * @return bool|array
	 */
	public function GetContactItemObjects($mUserId)
	{
		$mResult = false;
		try
		{
			$mResult = $this->oStorage->GetContactItemObjects($mUserId);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$mResult = false;
			$this->setLastException($oException);
		}

		return $mResult;
	}	

	/**
	 * @param int $iUserId
	 *
	 * @return \Aurora\Modules\Contacts\Classes\Contact|null
	 */
	public function GetMyGlobalContact($iUserId)
	{
		return $this->oStorage->GetMyGlobalContact($iUserId);
	}

	/**
	 * @param int $iUserId
	 * @param string $sSearch Default value is empty string
	 * @param string $sFirstCharacter Default value is empty string
	 *
	 * @return int
	 */
	public function getGroupItemsCount($iUserId, $sSearch = '', $sFirstCharacter = '')
	{
		$iResult = 0;
		try
		{
			$iResult = $this->oStorage->getGroupItemsCount($iUserId, $sSearch, $sFirstCharacter);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$iResult = 0;
			$this->setLastException($oException);
		}

		return $iResult;
	}

	/**
	 * @param int $iUserId
	 * @param int $iSortField Default value is **\Aurora\Modules\Contacts\Enums\SortField::Name 1**,
	 * @param int $iSortOrder Default value is **\Aurora\System\Enums\SortOrder::ASC 0**,
	 * @param int $iOffset Default value is **0**
	 * @param int $iRequestLimit Default value is **20**
	 * @param string $sSearch Default value is empty string
	 * @param string $sFirstCharacter Default value is empty string
	 * @param int $iContactId Default value is **0**
	 *
	 * @return bool|array
	 */
	public function getGroupItems($iUserId,
		$iSortField = \Aurora\Modules\Contacts\Enums\SortField::Name, $iSortOrder = \Aurora\System\Enums\SortOrder::ASC,
		$iOffset = 0, $iRequestLimit = 20, $sSearch = '', $sFirstCharacter = '', $iContactId = 0)
	{
		$mResult = false;
		try
		{
			$mResult = $this->oStorage->getGroupItems($iUserId, $iSortField, $iSortOrder,
				$iOffset, $iRequestLimit, $sSearch, $sFirstCharacter, $iContactId);
			//TODO add ability to find by firs character
//			$mResult = $this->oEavManager->getObjects(
//				'\Aurora\Modules\Contacts\Classes\Contact', 
//				array(
//					'Name'
//				),
//				$iOffset,
//				$iRequestLimit,
//				array('Name' => '%'.$sSearch.'%'),
//				$iSortField,
//				$iSortOrder
//			);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$mResult = false;
			$this->setLastException($oException);
		}

		return $mResult;
	}

	/**
	 * @param \Aurora\Modules\StandardAuth\Classes\Account $oAccount
	 *
	 * @return bool|array
	 */
	public function GetAllContactsNamesWithPhones($oAccount)
	{
		$mResult = false;
		try
		{
			$mResult = array(); // TODO
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$mResult = false;
			$this->setLastException($oException);
		}

		return $mResult;
	}

	/**
	 * @param \Aurora\Modules\Contacts\Classes\Contact $oContact
	 *
	 * @return bool
	 */
	public function createContact($oContact)
	{
		$bResult = false;
		try
		{
			if ($oContact->validate())
			{
				$bResult = $this->oStorage->createContact($oContact);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}

		if ($bResult)
		{
//			$oApiVoiceManager = /* @var $oApiVoiceManager \CApiVoiceManager */\Aurora\System\Api::Manager('voice');
//			if ($oApiVoiceManager)
//			{
//				$oApiVoiceManager->flushCallersNumbersCache($oContact->IdUser);
//			}
		}

		return $bResult;
	}
	
	public function copyContact($iUserId, $sUID, $sFromAddressbook, $sToAddressbook)
	{
		$mResult = false;
		try
		{
			$mResult = $this->oStorage->copyContact($iUserId, $sUID, $sFromAddressbook, $sToAddressbook);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$mResult = false;
			$this->setLastException($oException);
		}

		return $mResult;
	}

	/**
	 * @param \Aurora\Modules\Contacts\Classes\Group $oGroup
	 *
	 * @return bool
	 */
	public function createGroup($oGroup)
	{
		$bResult = false;
		try
		{
			if ($oGroup->validate())
			{
				$bResult = $this->oStorage->createGroup($oGroup);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}

	/**
	 * @param int $iUserId
	 * @param array $aContactIds
	 *
	 * @return bool
	 */
	public function deleteContacts($iUserId, $aContactIds, $sAddressBook = \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->deleteContacts($iUserId, $aContactIds, $sAddressBook);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}

		if ($bResult)
		{
//			$oApiVoiceManager = /* @var $oApiVoiceManager \CApiVoiceManager */\Aurora\System\Api::Manager('voice');
//			if ($oApiVoiceManager)
//			{
//				$oApiVoiceManager->flushCallersNumbersCache($iUserId);
//			}
		}

		return $bResult;
	}

	/**
	 * @param int $iUserId
	 * @param array $aContactIds
	 *
	 * @return bool
	 */
	public function deleteSuggestContacts($iUserId, $aContactIds)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->deleteSuggestContacts($iUserId, $aContactIds);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}

	/**
	 * @param int $iUserId
	 * @param array $aGroupIds
	 *
	 * @return bool
	 */
	public function deleteGroups($iUserId, $aGroupIds)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->deleteGroups($iUserId, $aGroupIds);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}

	/**
	 * @param int $iUserId
	 * @param mixed $mGroupId
	 *
	 * @return bool
	 */
	public function deleteGroup($iUserId, $mGroupId)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->deleteGroup($iUserId, $mGroupId);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}

	/**
	 * @param int $iUserId
	 * @param array $aEmails
	 *
	 * @return bool
	 */
	function updateSuggestTable($iUserId, $aEmails)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->updateSuggestTable($iUserId, $aEmails);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}

	/**
	 * @param int $iUserId
	 *
	 * @return bool
	 */
	public function clearAllContactsAndGroups($iUserId)
	{
		$bResult = $this->oStorage->clearAllContactsAndGroups($iUserId);

		return $bResult;
	}

	/**
	 * @return bool
	 */
	public function flushContacts()
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->flushContacts();
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}

	/**
	 * @param \Aurora\Modules\Contacts\Classes\Group $oGroup
	 * @param array $aContactIds
	 *
	 * @return bool
	 */
	public function addContactsToGroup($oGroup, $aContactIds)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->addContactsToGroup($oGroup, $aContactIds);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}

	/**
	 * @param \Aurora\Modules\Contacts\Classes\Group $oGroup
	 * @param array $aContactIds
	 *
	 * @return bool
	 */
	public function removeContactsFromGroup($oGroup, $aContactIds)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->removeContactsFromGroup($oGroup, $aContactIds);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}

	/**
	 * @param \Aurora\Modules\StandardAuth\Classes\Account $oAccount
	 * @param mixed $mContactId
	 * @param string $sStorage
	 *
	 * @return mixed
	 */
	public function ConvertedContactLocalId($oAccount, $mContactId, $sStorage = 'global')
	{
		$mResult = null;
		try
		{
			$mResult = $this->oStorage->ConvertedContactLocalId($oAccount, $mContactId, $sStorage);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $mResult;
	}

	/**
	 * @param \Aurora\Modules\StandardAuth\Classes\Account $oAccount
	 * @param string $sStorage
	 *
	 * @return mixed
	 */
	public function ConvertedContactLocalIdCollection($oAccount, $sStorage = 'global')
	{
		$aResult = array();
		try
		{
			$aResult = $this->oStorage->ConvertedContactLocalIdCollection($oAccount, $sStorage);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $aResult;
	}

	/**
	 * @param array $aIds
	 *
	 * @return mixed
	 */
	public function ContactIdsLinkedToGroups($aIds)
	{
		$aResult = array();
		try
		{
			$aResult = $this->oStorage->ContactIdsLinkedToGroups($aIds);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $aResult;
	}

	/**
	 * @param int $iUserId
	 * @param mixed $mContactId
	 *
	 * @return \Aurora\Modules\Contacts\Classes\Contact|bool
	 */
	public function GetGlobalContactById($iUserId, $mContactId)
	{
		$oContact = null;
		try
		{
			$oContact = $this->oStorage->GetGlobalContactById($iUserId, $mContactId);
			if ($oContact)
			{
				$mGroupIds = $this->getContactGroupIds($oContact);
				if (is_array($mGroupIds))
				{
					$oContact->GroupIds = $mGroupIds;
				}
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$oContact = false;
			$this->setLastException($oException);
		}

		return $oContact;
	}

	/**
	 * @param int $iGroupId
	 *
	 * @return bool
	 */
	public function getGroupEvents($iGroupId)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->getGroupEvents($iGroupId);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}

	/**
	 * @param string $sCalendarId
	 * @param string $sEventId
	 *
	 * @return bool
	 */
	public function getGroupEvent($sCalendarId, $sEventId)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->getGroupEvent($sCalendarId, $sEventId);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}

	/**
	 * @param int $iGroupId
	 * @param string $sCalendarId
	 * @param string $sEventId
	 *
	 * @return bool
	 */
	public function addEventToGroup($iGroupId, $sCalendarId, $sEventId)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->addEventToGroup($iGroupId, $sCalendarId, $sEventId);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}

	/**
	 * @param int $iGroupId
	 * @param string $sCalendarId
	 * @param string $sEventId
	 *
	 * @return bool
	 */
	public function removeEventFromGroup($iGroupId, $sCalendarId, $sEventId)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->removeEventFromGroup($iGroupId, $sCalendarId, $sEventId);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}

	/**
	 * @param string $sCalendarId
	 * @param string $sEventId
	 *
	 * @return bool
	 */
	public function removeEventFromAllGroups($sCalendarId, $sEventId)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->removeEventFromAllGroups($sCalendarId, $sEventId);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}

	public function getAddressBook($iUserId, $sUri)
	{
		$mResult = false;
		try
		{
			$mResult = $this->oStorage->getAddressBook($iUserId, $sUri);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$mResult = false;
			$this->setLastException($oException);
		}
		return $mResult;
	}

	public function createAddressBook($iUserId, $sUri, $sName)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->createAddressBook($iUserId, $sUri, $sName);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}

	public function updateAddressBook($iUserId, $sName, $sNewName)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->updateAddressBook($iUserId, $sName, $sNewName);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}

	public function deleteAddressBook($iUserId, $sName)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->deleteAddressBook($iUserId, $sName);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}
}

