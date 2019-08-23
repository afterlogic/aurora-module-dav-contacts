<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\DavContacts;

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

	protected $aRequireModules = array(
		'Contacts'
	);
	
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
		$this->subscribeEvent('Contacts::DeleteGroup::after', array($this, 'onAfterDeleteGroup'));

		$this->subscribeEvent('Contacts::AddContactsToGroup::after', array($this, 'onAfterAddContactsToGroup'));
		$this->subscribeEvent('Contacts::RemoveContactsFromGroup::after', array($this, 'onAfterRemoveContactsFromGroup'));
		$this->subscribeEvent('Core::DeleteUser::before', array($this, 'onBeforeDeleteUser'));
		$this->subscribeEvent('Contacts::UpdateSharedContacts::before', array($this, 'onBeforeUpdateSharedContacts'));
		
		$this->subscribeEvent('MobileSync::GetInfo', array($this, 'onGetMobileSyncInfo'));

		$this->subscribeEvent('Contacts::GetContactAsVCF::before', array($this, 'onBeforeGetContactAsVCF'));
	}
	
	/**
	 * 
	 * @param type $sUID
	 */
	protected function getContact($iUserId, $sStorage, $sUID)
	{
		$mResult = false;
		
		$oEavManager = \Aurora\System\Managers\Eav::getInstance();
		$aEntities = $oEavManager->getEntities(
			\Aurora\Modules\Contacts\Classes\Contact::class, 
			[], 
			0, 
			1,
			[
				'IdUser' => $iUserId,
				'Storage' => $sStorage,
				self::GetName() . '::UID' => $sUID
			]
		);
		if (is_array($aEntities) && count($aEntities) > 0)
		{
			$mResult = $aEntities[0];
		}
		
		return $mResult;
	}	

	/**
	 * 
	 * @param type $sUID
	 */
	protected function getGroup($iUserId, $sUID)
	{
		$mResult = false;
		
		$oEavManager = \Aurora\System\Managers\Eav::getInstance();
		$aEntities = $oEavManager->getEntities(
			\Aurora\Modules\Contacts\Classes\Group::class, 
			[], 
			0, 
			1,
			[
				'IdUser' => $iUserId,
				self::GetName() . '::UID' => $sUID
			]
		);
		if (is_array($aEntities) && count($aEntities) > 0)
		{
			$mResult = $aEntities[0];
		}
		
		return $mResult;
	}	
	
	protected function getGroupsContacts($sUUID)
	{
		$oEavManager = \Aurora\System\Managers\Eav::getInstance();
		return $oEavManager->getEntities(
			\Aurora\Modules\Contacts\Classes\GroupContact::class,
			['GroupUUID', 'ContactUUID'], 0, 0, ['ContactUUID' => $sUUID]);		
	}
	
	protected function getStorage($sStorage)
	{
		$sResult = \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME;
		if ($sStorage === 'personal')
		{
			$sResult = \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME;
		}
		else if ($sStorage === 'shared')
		{
			$sResult = \Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME;
		}
		else if ($sStorage === 'collected')
		{
			$sResult = \Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME;
		}
		else if ($sStorage === 'team')
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
	public function CreateContact($UserId, $VCard, $UID, $Storage = 'personal')
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		$oVCard = \Sabre\VObject\Reader::read($VCard, \Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES);
		$oContactsDecorator = \Aurora\Modules\Contacts\Module::Decorator();
		
		$bIsAuto = false;
		if ($Storage === 'collected')
		{
			$bIsAuto = true;
			$Storage = 'personal';
		}
		
		$aContactData = \Aurora\Modules\Contacts\Classes\VCard\Helper::GetContactDataFromVcard($oVCard);
		$aContactData['Storage'] = $Storage;
		
		$this->__LOCK_AFTER_CREATE_CONTACT_SUBSCRIBE__ = true;
		$mResult = $oContactsDecorator->CreateContact($aContactData, $UserId);
		if ($mResult)
		{
			$oEavManager = \Aurora\System\Managers\Eav::getInstance();
			$oEntity = $oEavManager->getEntity(
				$mResult['UUID'],
				\Aurora\Modules\Contacts\Classes\Contact::class
			);
			if ($oEntity)
			{
				$oEntity->Auto = $bIsAuto;
				$oEntity->{self::GetName() . '::UID'} = $UID;
				$oEntity->{self::GetName() . '::VCardUID'} = \str_replace('urn:uuid:', '', (string) $oVCard->UID);
				$oEavManager->saveEntity($oEntity);
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
//			$aGroupsContacts = $this->getGroupsContacts($oContact->UUID);
			$bIsAuto = false;
			if ($Storage === 'collected')
			{
				$bIsAuto = true;
				$Storage = 'personal';
			}

			$oEavManager = \Aurora\System\Managers\Eav::getInstance();
			$oContact->populate($aContactData);
			$oContact->Storage = $Storage;
			$mResult = $oEavManager->saveEntity($oContact);

/*			
			if ($mResult)
			{
				\Aurora\System\Api::GetModule('Contacts')->getManager()->updateContactGroups($oContact);
				
				$oContactsModuleDecorator = \Aurora\System\Api::GetModuleDecorator('Contacts');

				foreach ($aGroupsContacts as $oGroupsContact)
				{
					$aContacts = $oContactsModuleDecorator->GetContacts('all', 0, 0, \Aurora\Modules\Contacts\Enums\SortField::Name, \Aurora\System\Enums\SortOrder::ASC, '', $oGroupsContact->GroupUUID);
					if (isset($aContacts['ContactCount']) && (int) $aContacts['ContactCount'] === 0)
					{
						$oContactsModuleDecorator->DeleteGroup($oGroupsContact->GroupUUID);
					}
				}
			}
*/			
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

		$aGroupData['Contacts'] = \Aurora\System\Managers\Eav::getInstance()->getEntitiesUids(
			\Aurora\Modules\Contacts\Classes\Contact::class, 
			0, 
			0,
			['DavContacts::VCardUID' => [$aGroupData['Contacts'], 'IN']]
		);

		$oGroupDb = $this->getGroup($UserId, $UUID);

		$aGroupData['UUID'] = $oGroupDb->UUID;
		
		$mResult = \Aurora\Modules\Contacts\Module::getInstance()->UpdateGroup($aGroupData, $UserId);
		
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
				$oContact = \Aurora\System\Api::GetModule('Contacts')->GetContact($sUUID);
				if ($oContact instanceof \Aurora\Modules\Contacts\Classes\Contact)
				{
					$oContact->{self::GetName() . '::UID'} = $sUUID;
					$oContact->{self::GetName() . '::VCardUID'} = $sUUID;

					\Aurora\System\Managers\Eav::getInstance()->saveEntity($oContact);
					if (!$this->getManager()->createContact($oContact))
					{
						$aResult = false;
					}
					else
					{
						foreach ($oContact->GroupsContacts as $oGroupContact)
						{
							$oGroup = \Aurora\Modules\Contacts\Module::getInstance()->GetGroup(
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
				$oContact = \Aurora\Modules\Contacts\Module::Decorator()->GetContact($aArgs['Contact']['UUID']);
				if ($oContact instanceof \Aurora\Modules\Contacts\Classes\Contact)
				{
					$UserId = \Aurora\System\Api::getAuthenticatedUserId();
					
					$oDavContact = $this->getManager()->getContactById($UserId, $oContact->{self::GetName() . '::UID'}, $this->getStorage($aArgs['Contact']['Storage']));
					
					if ($oDavContact)
					{
						if (!$this->getManager()->updateContact($oContact))
						{
							$aResult = false;
						}
						else
						{
							foreach ($oContact->GroupsContacts as $oGroupsContact)
							{
								$oGroup = \Aurora\System\Managers\Eav::getInstance()->getEntity(
									$oGroupsContact->GroupUUID, 
									\Aurora\Modules\Contacts\Classes\Group::class
								);
								if ($oGroup)
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

		if (isset($aArgs['UUIDs']))
		{
			$oEavManager = \Aurora\System\Managers\Eav::getInstance();
			$aEntities = $oEavManager->getEntities(
				\Aurora\Modules\Contacts\Classes\Contact::class, 
				['DavContacts::UID', 'Storage'], 
				0, 
				0,
				['UUID' => [\array_unique($aArgs['UUIDs']), 'IN']]
			);
			$aUIDs = [];
			$sStorage = 'personal';
			foreach ($aEntities as $oContact)
			{
				$aUIDs[] = $oContact->{'DavContacts::UID'};
				$sStorage = $oContact->Storage;
			}
			if ($sStorage !== 'team')
			{
				if (!$this->getManager()->deleteContacts(
						\Aurora\System\Api::getAuthenticatedUserId(),
						$aUIDs,
						$this->getStorage($sStorage))
				)
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
			$oGroup = \Aurora\System\Api::GetModule('Contacts')->GetGroup($sUUID);
			if ($oGroup instanceof \Aurora\Modules\Contacts\Classes\Group)
			{
				$oGroup->{self::GetName() . '::UID'} = $sUUID;

				\Aurora\System\Managers\Eav::getInstance()->saveEntity($oGroup);
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
			$oGroup = \Aurora\System\Api::GetModule('Contacts')->GetGroup($sUUID);
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
	public function onAfterDeleteGroup(&$aArgs, &$aResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$aResult = $this->getManager()->deleteGroup($aArgs['UserId'], $aArgs['UUID']);
	}
	
	/**
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onAfterAddContactsToGroup(&$aArgs, &$aResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		$ContactUUIDs = $aArgs['ContactUUIDs'];
		$oGroup = \Aurora\Modules\Contacts\Module::Decorator()->GetGroup($aArgs['GroupUUID']);
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

		$oGroup = \Aurora\Modules\Contacts\Module::Decorator()->GetGroup($aArgs['GroupUUID']);
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
		$oContacts = \Aurora\System\Api::GetModuleDecorator('Contacts');
		{
			$aUUIDs = isset($aArgs['UUIDs']) ? $aArgs['UUIDs'] : [];
			foreach ($aUUIDs as $sUUID)
			{
				$oContact = $oContacts->GetContact($sUUID);
				if ($oContact)
				{
					if ($oContact->Storage === 'shared')
					{
						$this->getManager()->copyContact(
								\Aurora\System\Api::getAuthenticatedUserId(), 
								$oContact->{'DavContacts' . '::UID'}, 
								\Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME,
								\Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME
						);
					}
					else if ($oContact->Storage === 'personal')
					{
						$this->getManager()->copyContact(
								\Aurora\System\Api::getAuthenticatedUserId(), 
								$oContact->{'DavContacts' . '::UID'}, 
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
	
}
