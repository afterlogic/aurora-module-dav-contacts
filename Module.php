<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\DavContacts;

use Aurora\Modules\Contacts\Classes\AddressBook;
use \Aurora\Modules\Contacts\Enums\StorageType;
use Aurora\System\EAV\Query;

/**
 * Adds ability to work with Dav Contacts.
 * 
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	public $oManager = null;

	protected $aRequireModules = [
		'Contacts'
	];
	
	protected $_oldGroup = null;
	
	protected $__LOCK_AFTER_CREATE_CONTACT_SUBSCRIBE__ = false;
	protected $__LOCK_AFTER_UPDATE_CONTACT_SUBSCRIBE__ = false;

	public function getManager()
	{
		if ($this->oManager === null)
		{
			$this->oManager = new Manager($this);
		}

		return $this->oManager;
	}

	public function init() 
	{
		\Aurora\Modules\Contacts\Classes\Contact::extend(
			self::GetName(),
			[
				'UID' => ['string', '']
			]

		);		
		\Aurora\Modules\Contacts\Classes\Contact::extend(
			self::GetName(),
			[
				'VCardUID' => ['string', '']
			]

		);
		\Aurora\Modules\Contacts\Classes\Group::extend(
			self::GetName(),
			[
				'UID' => ['string', '']
			]

		);
		
		$this->subscribeEvent('Contacts::CreateContact::after', array($this, 'onAfterCreateContact'));
		$this->subscribeEvent('Contacts::UpdateContact::after', array($this, 'onAfterUpdateContact'));
		$this->subscribeEvent('Contacts::DeleteContacts::before', array($this, 'onBeforeDeleteContacts'));

		$this->subscribeEvent('Contacts::CreateGroup::after', array($this, 'onAfterCreateGroup'));
		
		$this->subscribeEvent('Contacts::UpdateGroup::before', array($this, 'onBeforeUpdateGroup'));
		$this->subscribeEvent('Contacts::UpdateGroup::after', array($this, 'onAfterUpdateGroup'));
		
		$this->subscribeEvent('Contacts::DeleteGroup::before', array($this, 'onBeforDeleteGroup'));
//		$this->subscribeEvent('Contacts::DeleteGroup::after', array($this, 'onAfterDeleteGroup'));

		$this->subscribeEvent('Contacts::AddContactsToGroup::after', array($this, 'onAfterAddContactsToGroup'));
		$this->subscribeEvent('Contacts::RemoveContactsFromGroup::after', array($this, 'onAfterRemoveContactsFromGroup'));
		$this->subscribeEvent('Core::DeleteUser::before', array($this, 'onBeforeDeleteUser'));
		$this->subscribeEvent('Contacts::UpdateSharedContacts::before', array($this, 'onBeforeUpdateSharedContacts'));
		
		$this->subscribeEvent('MobileSync::GetInfo', array($this, 'onGetMobileSyncInfo'));

		$this->subscribeEvent('Contacts::GetContactAsVCF::before', array($this, 'onBeforeGetContactAsVCF'));

		$this->subscribeEvent('Contacts::CreateAddressBook::after', array($this, 'onAfterCreateAddressBook'));
		$this->subscribeEvent('Contacts::UpdateAddressBook::after', array($this, 'onAfterUpdateAddressBook'));
		$this->subscribeEvent('Contacts::DeleteAddressBook::before', array($this, 'onBeforeDeleteAddressBook'));
	}
	
	/**
	 * 
	 * @param type $sUID
	 */
	protected function getContact($iUserId, $sStorage, $sUID)
	{
		return (new \Aurora\System\EAV\Query(\Aurora\Modules\Contacts\Classes\Contact::class))
			->where([
				'$AND' => [
					'IdUser' => $iUserId,
					'Storage' => $sStorage,
					self::GetName() . '::UID' => $sUID
				]
			])
			->limit(1)
			->one()
			->exec();
	}	

	/**
	 * 
	 * @param type $sUID
	 */
	protected function getGroup($iUserId, $sUID)
	{
		return (new \Aurora\System\EAV\Query(\Aurora\Modules\Contacts\Classes\Group::class))
			->where([
				'$AND' => [
					'IdUser' => $iUserId, 
					self::GetName() . '::UID' => $sUID
				]
			])
			->limit(1)
			->one()
			->exec();
	}	
	
	protected function getStorage($sStorage)
	{
		$sResult = \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME;
		if ($sStorage === StorageType::Personal)
		{
			$sResult = \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME;
		}
		else if ($sStorage === StorageType::Shared)
		{
			$sResult = \Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME;
		}
		else if ($sStorage === StorageType::Collected)
		{
			$sResult = \Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME;
		}
		else if ($sStorage === StorageType::Team)
		{
			$sResult = 'gab';
		}
		
		return $sResult;
	}	
	
	/**
	 * 
	 * @param int $UserId
	 * @param string $VCard
	 * @return bool|string
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function CreateContact($UserId, $VCard, $UID, $Storage = StorageType::Personal)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		$oVCard = \Sabre\VObject\Reader::read($VCard, \Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES);
		$oContactsDecorator = \Aurora\Modules\Contacts\Module::Decorator();
		
		$bIsAuto = false;
		if ($Storage === StorageType::Collected)
		{
			$bIsAuto = true;
			$Storage = StorageType::Personal;
		}
		
		$aContactData = \Aurora\Modules\Contacts\Classes\VCard\Helper::GetContactDataFromVcard($oVCard);
		$aContactData['Storage'] = $Storage;
		
		$this->__LOCK_AFTER_CREATE_CONTACT_SUBSCRIBE__ = true;
		$mResult = $oContactsDecorator->CreateContact($aContactData, $UserId);
		if ($mResult)
		{
			$oContact = \Aurora\Modules\Contacts\Module::getInstance()->GetContact($mResult['UUID'], $UserId);

			if ($oContact instanceof \Aurora\Modules\Contacts\Classes\Contact)
			{
				$oContact->Auto = $bIsAuto;
				$oContact->{self::GetName() . '::UID'} = $UID;
				$oContact->{self::GetName() . '::VCardUID'} = \str_replace('urn:uuid:', '', (string) $oVCard->UID);
				$oContact->saveAttributes([
					'Auto',
					self::GetName() . '::UID',
					self::GetName() . '::VCardUID'
				]);
			}
		}
		
		$this->__LOCK_AFTER_CREATE_CONTACT_SUBSCRIBE__ = false;
		
		return $mResult;
	}	

	/**
	 * 
	 * @param int $UserId
	 * @param string $VCard
	 * @return bool|string
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function CreateGroup($UserId, $VCard, $UUID)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		$oVCard = \Sabre\VObject\Reader::read($VCard, \Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES);
		
		$aGroupData = \Aurora\Modules\Contacts\Classes\VCard\Helper::GetGroupDataFromVcard($oVCard, $UUID);

		if (isset($aGroupData['Contacts']) && is_array($aGroupData['Contacts']) && count($aGroupData['Contacts']) > 0)
		{
			$aGroupData['Contacts'] = \Aurora\System\Managers\Eav::getInstance()->getEntitiesUids(
				\Aurora\Modules\Contacts\Classes\Contact::class, 
				0, 
				0,
				['DavContacts::VCardUID' => [$aGroupData['Contacts'], 'IN']]
			);
		}

		if (isset($UUID))
		{
			$aGroupData['DavContacts::UID'] = $UUID;
		}
		
		$mResult = \Aurora\Modules\Contacts\Module::getInstance()->CreateGroup($aGroupData, $UserId);
		
		return $mResult;
	}		
	
	/**
	 * 
	 * @param string $VCard
	 * @return bool|string
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function UpdateContact($UserId, $VCard, $UUID, $Storage = 'personal')
	{
		$mResult = false;
		
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		$oVCard = \Sabre\VObject\Reader::read($VCard, \Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES);
		$aContactData = \Aurora\Modules\Contacts\Classes\VCard\Helper::GetContactDataFromVcard($oVCard);

		$this->__LOCK_AFTER_UPDATE_CONTACT_SUBSCRIBE__ = true;
		/* @var $oContact \Aurora\Modules\Contacts\Classes\Contact */
		$oContact = $this->getContact($UserId, $Storage, $UUID);
		
		if ($oContact)
		{
			$bIsAuto = false;
			if ($Storage === StorageType::Collected)
			{
				$bIsAuto = true;
				$Storage = StorageType::Personal;
			}

			$oContact->populate($aContactData);
			$oContact->Storage = $Storage;
			$oContact->Auto = $bIsAuto;
			$mResult = $oContact->save();
		}
		$this->__LOCK_AFTER_UPDATE_CONTACT_SUBSCRIBE__ = false;
		
		return $mResult;
	}	

	/**
	 * 
	 * @param int $UserId
	 * @param string $VCard
	 * @return bool|string
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function UpdateGroup($UserId, $VCard, $UUID)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		$oVCard = \Sabre\VObject\Reader::read($VCard, \Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES);
		
		$aGroupData = \Aurora\Modules\Contacts\Classes\VCard\Helper::GetGroupDataFromVcard($oVCard, $UUID);

		if (is_array($aGroupData['Contacts']) && count($aGroupData['Contacts']) > 0)
		{
			$aGroupData['Contacts'] = (new \Aurora\System\EAV\Query(\Aurora\Modules\Contacts\Classes\Contact::class))
				->where(['DavContacts::VCardUID' => [$aGroupData['Contacts'], 'IN']])
				->onlyUUIDs()
				->exec();
		}
		else
		{
			$aGroupData['Contacts'] = [];
		}

		$oGroupDb = $this->getGroup($UserId, $UUID);

		$aGroupData['UUID'] = $oGroupDb->UUID;
		
		$mResult = \Aurora\Modules\Contacts\Module::getInstance()->UpdateGroup($UserId, $aGroupData);
		
		return $mResult;
	}		
	
	/**
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onAfterCreateContact(&$aArgs, &$aResult)
	{
		if (!$this->__LOCK_AFTER_CREATE_CONTACT_SUBSCRIBE__ && isset($aArgs["Contact"]["Storage"]))
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
			$sUUID = isset($aResult) && isset($aResult['UUID'])? $aResult['UUID'] : false;
			if ($sUUID)
			{
				$oContact = \Aurora\Modules\Contacts\Module::getInstance()->GetContact($sUUID, $aArgs['UserId']);
				if ($oContact instanceof \Aurora\Modules\Contacts\Classes\Contact)
				{
					$oContact->{self::GetName() . '::UID'} = $sUUID;
					$oContact->{self::GetName() . '::VCardUID'} = $sUUID;
					$oContact->saveAttributes([
						self::GetName() . '::UID',
						self::GetName() . '::VCardUID'
					]);
					
					if (strlen($oContact->Storage) > 11 && substr($oContact->Storage, 0, 11) === 'addressbook') {
						$oContact->Storage = 'addressbook';
					}
					if (!$this->getManager()->createContact($oContact))
					{
						$aResult = false;
					}
					else
					{
						foreach ($oContact->GroupsContacts as $oGroupContact)
						{
							$oGroup = \Aurora\Modules\Contacts\Module::getInstance()->GetGroup(
								$aArgs['UserId'],
								$oGroupContact->GroupUUID
							);
							if ($oGroup)
							{
								$this->getManager()->updateGroup($oGroup);
							}
						}
					}
				}
			}
		}
	}	
	
	/**
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onAfterUpdateContact(&$aArgs, &$aResult)
	{
		if (!$this->__LOCK_AFTER_CREATE_CONTACT_SUBSCRIBE__)
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

			if($aResult && is_array($aArgs['Contact']) && isset($aArgs['Contact']['UUID']))
			{
				$UserId = $aArgs['UserId'];
				$oContact = \Aurora\Modules\Contacts\Module::Decorator()->GetContact($aArgs['Contact']['UUID'], $UserId);
				if ($oContact instanceof \Aurora\Modules\Contacts\Classes\Contact)
				{
					$sContactStorage = $aArgs['Contact']['Storage'];
					if ($sContactStorage === StorageType::Personal && isset($aArgs['Contact']['Auto']) && $aArgs['Contact']['Auto'] === true)
					{
						$sContactStorage = StorageType::Collected;
					}
					$sStorage = $this->getStorage($sContactStorage);
					if (strlen($sContactStorage) >= 11 && substr($sContactStorage, 0, 11) === 'addressbook') 
					{
						$oEavAddressBook = (new Query(AddressBook::class))->where([
							'EntityId' => $oContact->AddressBookId,
							'IdUser' => $UserId
						])->one()->exec();
						if ($oEavAddressBook)
						{
							$sStorage =  $oEavAddressBook->UUID;
						}
						
					}
					$oDavContact = $this->getManager()->getContactById(
						$UserId, 
						$oContact->{self::GetName() . '::UID'}, 
						$sStorage
					);
					
					if ($oDavContact)
					{
						if (strlen($oContact->Storage) > 11 && substr($oContact->Storage, 0, 11) === 'addressbook') {
							$oContact->Storage = 'addressbook';
						}
						if (!$this->getManager()->updateContact($oContact))
						{
							$aResult = false;
						}
						else
						{
							foreach ($oContact->GroupsContacts as $oGroupsContact)
							{
								$oGroup = \Aurora\Modules\Contacts\Module::Decorator()->GetGroup($UserId, $oGroupsContact->GroupUUID);
								if ($oGroup instanceof \Aurora\Modules\Contacts\Classes\Group)
								{
									$this->getManager()->updateGroup($oGroup);
								}
							}
						}
					}
					else
					{
						if (!$this->getManager()->createContact($oContact))
						{
							$aResult = false;
						}
					}
				}			
			}
		}
	}
	
	/**
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onBeforeDeleteContacts(&$aArgs, &$aResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		\Aurora\Modules\Contacts\Module::getInstance()->CheckAccess($aArgs['UserId']);
		$oUser = \Aurora\System\Api::getUserById($aArgs['UserId']);

		if (isset($aArgs['UUIDs']))
		{
			$aEntities = (new \Aurora\System\EAV\Query(\Aurora\Modules\Contacts\Classes\Contact::class))
				->select(['DavContacts::UID', 'Storage', 'IdUser', 'AddressBookId', 'Auto'])
				->where(['UUID' => [\array_unique($aArgs['UUIDs']), 'IN']])
				->exec();

			$aUIDs = [];
			$sStorage = $sContactStorage = StorageType::Personal;
			$bIsAuto = false;
			$iAddressBookId = 0;
			foreach ($aEntities as $oContact)
			{
				if (\Aurora\Modules\Contacts\Module::Decorator()->CheckAccessToObject($oUser, $oContact))
				{
					$aUIDs[] = $oContact->{'DavContacts::UID'};
					$sStorage = $sContactStorage = $oContact->Storage; // TODO: sash04ek
					$bIsAuto = $oContact->Auto;

					$iAddressBookId = $oContact->AddressBookId;
				}
			}
			if ($sStorage !== StorageType::Team)
			{
				$sStorage = $this->getStorage($sStorage);
				if (strlen($sContactStorage) >= 11 && substr($sContactStorage, 0, 11) === 'addressbook') 
				{
					$oEavAddressBook = (new Query(AddressBook::class))->where([
						'EntityId' => $iAddressBookId,
						'IdUser' => $aArgs['UserId']
					])->one()->exec();
					if ($oEavAddressBook)
					{
						$sStorage =  $oEavAddressBook->UUID;
					}
				}
				if ($bIsAuto) {
					$sStorage = $this->getStorage(StorageType::Collected);
				}
				if (!$this->getManager()->deleteContacts(
						$aArgs['UserId'],
						$aUIDs,
						$sStorage
				))
				{
					$aResult = false;
				}
			}
		}
	}	
	
	/**
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onAfterCreateGroup(&$aArgs, &$aResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$sUUID = $aResult;
		if ($sUUID)
		{
			$oGroup = \Aurora\Modules\Contacts\Module::getInstance()->GetGroup($aArgs['UserId'], $sUUID);
			if ($oGroup instanceof \Aurora\Modules\Contacts\Classes\Group)
			{
				$oGroup->{self::GetName() . '::UID'} = $sUUID;
				$oGroup->saveAttributes([self::GetName() . '::UID']);

				if (!$this->getManager()->createGroup($oGroup))
				{
					$aResult = false;
				}
			}
		}
	}

	/**
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onAfterUpdateGroup(&$aArgs, &$aResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$sUUID = isset($aArgs['Group']) && isset($aArgs['Group']['UUID'])? $aArgs['Group']['UUID'] : false;
		if ($sUUID)
		{
			$oGroup = \Aurora\Modules\Contacts\Module::getInstance()->GetGroup($aArgs['UserId'], $sUUID);
			if ($oGroup instanceof \Aurora\Modules\Contacts\Classes\Group)
			{
				if (!$this->getManager()->updateGroup($oGroup))
				{
					$aResult = false;
				}
			}
		}
	}

	/**
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onBeforDeleteGroup(&$aArgs, &$mResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$oGroup = \Aurora\Modules\Contacts\Module::getInstance()->GetGroup(
			$aArgs['UserId'],
			$aArgs['UUID']
		);

		if ($oGroup instanceof \Aurora\Modules\Contacts\Classes\Group)
		{
			$mResult = $this->getManager()->deleteGroup($aArgs['UserId'], $oGroup->{$this->GetName() . '::UID'});
		}
	}
	
	/**
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onAfterAddContactsToGroup(&$aArgs, &$aResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		$oGroup = \Aurora\Modules\Contacts\Module::Decorator()->GetGroup($aArgs['UserId'], $aArgs['GroupUUID']);
		if ($oGroup)
		{
			$this->getManager()->updateGroup($oGroup);
		}
	}
	
	/**
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onAfterRemoveContactsFromGroup(&$aArgs, &$aResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$oGroup = \Aurora\Modules\Contacts\Module::Decorator()->GetGroup($aArgs['UserId'], $aArgs['GroupUUID']);
		if ($oGroup)
		{
			$this->getManager()->updateGroup($oGroup);
		}
	}

	public function onBeforeDeleteUser(&$aArgs, &$mResult)
	{
		$oAuthenticatedUser = \Aurora\System\Api::getAuthenticatedUser();
		
		$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserUnchecked($aArgs['UserId']);
		
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User && $oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::TenantAdmin && $oUser->IdTenant === $oAuthenticatedUser->IdTenant)
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);
		}
		else
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);
		}
		
		$this->getManager()->clearAllContactsAndGroups($aArgs['UserId']);
	}
	
	public function onBeforeUpdateSharedContacts($aArgs, &$mResult)
	{
		$oContacts = \Aurora\Modules\Contacts\Module::Decorator();
		{
			$aUUIDs = isset($aArgs['UUIDs']) ? $aArgs['UUIDs'] : [];
			foreach ($aUUIDs as $sUUID)
			{
				$oContact = $oContacts->GetContact($sUUID);
				if ($oContact)
				{
					if ($oContact->Storage === StorageType::Shared)
					{
						$this->getManager()->copyContact(
								$aArgs['UserId'], 
								$oContact->{'DavContacts::UID'}, 
								\Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME,
								\Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME
						);
					}
					else if ($oContact->Storage === StorageType::Personal)
					{
						$this->getManager()->copyContact(
								$aArgs['UserId'], 
								$oContact->{'DavContacts::UID'}, 
								\Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME, 
								\Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME 
						);
					}
				}
			}
		}
	}
	
    public function onGetMobileSyncInfo($aArgs, &$mResult)
	{
		$oDavModule = \Aurora\Modules\Dav\Module::Decorator();

		$sDavServer = $oDavModule->GetServerUrl();
		
		$mResult['Dav']['Contacts'] = array(
			'PersonalContactsUrl' => $sDavServer.'addressbooks/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME,
			'CollectedAddressesUrl' => $sDavServer.'addressbooks/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME,
			'SharedWithAllUrl' => $sDavServer.'addressbooks/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME,
			'TeamAddressBookUrl' => $sDavServer.'gab'
		);
	}	

	public function onBeforeGetContactAsVCF($aArgs, &$mResult)
	{
		$oContact = $aArgs['Contact'];
		if ($oContact instanceof \Aurora\Modules\Contacts\Classes\Contact)
		{
			$mResult = $this->getManager()->getVCardObjectById($oContact->IdUser, $oContact->{'DavContacts::UID'}, $this->getStorage($oContact->Storage));

			return true;
		}
	}

	public function onAfterCreateAddressBook($aArgs, &$mResult)
	{
		if ($mResult)
		{
			$oEavAddressBook = (new Query(\Aurora\Modules\Contacts\Classes\AddressBook::class))->where([
				'EntityId' => $mResult,
				'IdUser' => $aArgs['UserId']
			])->one()->exec();
			if ($oEavAddressBook)
			{
				$mResult = $this->getManager()->createAddressBook(
					$aArgs['UserId'], 
					$oEavAddressBook->UUID, 
					$oEavAddressBook->Name
				);
			}
		}
		
		return true;
	}

	public function onAfterUpdateAddressBook($aArgs, &$mResult)
	{
		if ($mResult)
		{
			$oEavAddressBook = (new Query(\Aurora\Modules\Contacts\Classes\AddressBook::class))->where([
				'EntityId' => $aArgs['EntityId'],
				'IdUser' => $aArgs['UserId']
			])->one()->exec();
			if ($oEavAddressBook)
			{
				$mResult = $this->getManager()->updateAddressBook(
					$aArgs['UserId'], 
					$oEavAddressBook->UUID,  
					$aArgs['AddressBookName']
				);
			}
		}
	}

	public function onBeforeDeleteAddressBook($aArgs, &$mResult)
	{
		$oEavAddressBook = (new Query(\Aurora\Modules\Contacts\Classes\AddressBook::class))->where([
			'EntityId' => $aArgs['EntityId'],
			'IdUser' => $aArgs['UserId']
		])->one()->exec();
		if ($oEavAddressBook)
		{
			$mResult = $this->getManager()->deleteAddressBook(
				$aArgs['UserId'], 
				$oEavAddressBook->UUID
			);
		}
	}
	
}
