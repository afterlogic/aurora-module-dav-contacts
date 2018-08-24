<?php
/**
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\DavContacts;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing AfterLogic Software License
 * @copyright Copyright (c) 2018, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	public $oApiContactsManager = null;

	protected $aRequireModules = array(
		'Contacts'
	);
	
	protected $_oldGroup = null;
	
	protected $__LOCK_AFTER_CREATE_CONTACT_SUBSCRIBE__ = false;
	protected $__LOCK_AFTER_UPDATE_CONTACT_SUBSCRIBE__ = false;

	public function init() 
	{
		$this->oApiContactsManager = new Manager($this);
		
		$this->extendObject(
			'Aurora\\Modules\\Contacts\\Classes\\Contact',
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
	}
	
	/**
	 * 
	 * @param type $sUID
	 */
	protected function getContact($iUserId, $sStorage, $sUID)
	{
		$mResult = false;
		
		$oEavManager = new \Aurora\System\Managers\Eav();
		$aEntities = $oEavManager->getEntities(
			'Aurora\\Modules\\Contacts\\Classes\\Contact', 
			[], 
			0, 
			1,
			[
				'IdUser' => $iUserId,
				'Storage' => $sStorage,
				$this->GetName() . '::UID' => $sUID
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
		$oEavManager = new \Aurora\System\Managers\Eav();
		return $oEavManager->getEntities(
			'Aurora\\Modules\\Contacts\\Classes\\GroupContact',
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
		
		return $sResult;
	}	
	
	/**
	 * 
	 * @param int $UserId
	 * @param string $VCard
	 * @return bool|string
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function CreateContact($UserId, $VCard, $UUID, $Storage = 'personal')
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
			$oEavManager = new \Aurora\System\Managers\Eav();
			$oEntity = $oEavManager->getEntity(
				$mResult,
				'Aurora\\Modules\\Contacts\\Classes\\Contact'
			);
			if ($oEntity)
			{
				$oEntity->Auto = $bIsAuto;
				$oEntity->{$this->GetName() . '::UID'} = $UUID;
				$oEavManager->saveEntity($oEntity);
			}
		}
		
		$this->__LOCK_AFTER_CREATE_CONTACT_SUBSCRIBE__ = false;
		
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
			$aGroupsContacts = $this->getGroupsContacts($oContact->UUID);
			$bIsAuto = false;
			if ($Storage === 'collected')
			{
				$bIsAuto = true;
				$Storage = 'personal';
			}

			$oEavManager = new \Aurora\System\Managers\Eav();
			$oContact->populate($aContactData);
			$oContact->Storage = $Storage;
			$mResult = $oEavManager->saveEntity($oContact);
			if ($mResult)
			{
				\Aurora\System\Api::GetModule('Contacts')->oApiContactsManager->updateContactGroups($oContact);
				
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
		}
		$this->__LOCK_AFTER_UPDATE_CONTACT_SUBSCRIBE__ = false;
		
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
			$sUUID = isset($aResult) ? $aResult : false;
			if ($sUUID)
			{
				$oContact = \Aurora\Modules\Contacts\Module::Decorator()->GetContact($sUUID);
				if ($oContact instanceof \Aurora\Modules\Contacts\Classes\Contact)
				{
					$oContact->{$this->GetName() . '::UID'} = $sUUID;
					$oEavManager = new \Aurora\System\Managers\Eav();
					$oEavManager->saveEntity($oContact);
					if (!$this->oApiContactsManager->createContact($oContact))
					{
						$aResult = false;
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
					
					$oDavContact = $this->oApiContactsManager->getContactById($UserId, $oContact->{$this->GetName() . '::UID'}, $this->getStorage($aArgs['Contact']['Storage']));
					
					if ($oDavContact)
					{
						if (!$this->oApiContactsManager->updateContact($oContact))
						{
							$aResult = false;
						}
					}
					else
					{
						if (!$this->oApiContactsManager->createContact($oContact))
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
			$oEavManager = new \Aurora\System\Managers\Eav();
			$aEntities = $oEavManager->getEntities(
				'Aurora\\Modules\\Contacts\\Classes\\Contact', 
				['DavContacts::UID'], 
				0, 
				0,
				['UUID' => [\array_unique($aArgs['UUIDs']), 'IN']]
			);
			$aUIDs = [];
			foreach ($aEntities as $oContact)
			{
				$aUIDs[] = $oContact->{'DavContacts::UID'};
			}
			if (!$this->oApiContactsManager->deleteContacts(
				\Aurora\System\Api::getAuthenticatedUserId(),
				$aUIDs)
			)
			{
				$aResult = false;
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

		$aContacts = isset($aArgs['Group']['Contacts']) ? $aArgs['Group']['Contacts'] : [];
		if (is_array($aContacts) && count($aContacts) > 0)
		{
			foreach ($aContacts as $sUUID)
			{
				$oContact = \Aurora\Modules\Contacts\Module::Decorator()->GetContact($sUUID);
				if ($oContact)
				{
					$this->oApiContactsManager->updateContact($oContact);
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

		$aContacts = \Aurora\Modules\Contacts\Module::Decorator()->GetContacts('all', 0, 0, \Aurora\Modules\Contacts\Enums\SortField::Name, \Aurora\System\Enums\SortOrder::ASC, '', $aArgs['Group']['UUID']);
		if (isset($aContacts['List']) && is_array($aContacts['List']) && count($aContacts['List']))
		{
			foreach ($aContacts['List'] as $aContact)
			{
				$oContact = \Aurora\Modules\Contacts\Module::Decorator()->GetContact($aContact['UUID']);
				$this->oApiContactsManager->updateContact($oContact);
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

		$aContacts = \Aurora\Modules\Contacts\Module::Decorator()->GetContacts('all', 0, 0, \Aurora\Modules\Contacts\Enums\SortField::Name, \Aurora\System\Enums\SortOrder::ASC, '', $aArgs['UUID']);
		
		if (isset($aContacts['List']) && is_array($aContacts['List']) && count($aContacts['List']))
		{
			foreach ($aContacts['List'] as $aContact)
			{
				$oContact = \Aurora\Modules\Contacts\Module::Decorator()->GetContact($aContact['UUID']);
				$this->oApiContactsManager->updateContact($oContact);
			}
		}
	}
	
	/**
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onAfterAddContactsToGroup(&$aArgs, &$aResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		
		$ContactUUIDs = $aArgs['ContactUUIDs'];
		foreach ($ContactUUIDs as $sUUID)
		{
			$oContact = \Aurora\Modules\Contacts\Module::Decorator()->GetContact($sUUID);
			if ($oContact)
			{
				$this->oApiContactsManager->updateContact($oContact);
			}
		}
	}
	
	/**
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onAfterRemoveContactsFromGroup(&$aArgs, &$aResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

		$ContactUUIDs = $aArgs['ContactUUIDs'];
		foreach ($ContactUUIDs as $sUUID)
		{
			$oContact = \Aurora\Modules\Contacts\Module::Decorator()->GetContact($sUUID);
			if ($oContact)
			{
				$this->oApiContactsManager->updateContact($oContact);
			}
		}
	}

	public function onBeforeDeleteUser(&$aArgs, &$mResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);
		$this->oApiContactsManager->clearAllContactsAndGroups($aArgs['UserId']);
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
						$this->oApiContactsManager->copyContact(
								\Aurora\System\Api::getAuthenticatedUserId(), 
								$oContact->{'DavContacts' . '::UID'}, 
								\Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME,
								\Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME
						);
					}
					else if ($oContact->Storage === 'personal')
					{
						$this->oApiContactsManager->copyContact(
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
			'PersonalContactsUrl' => $sDavServer.'/addressbooks/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME,
			'CollectedAddressesUrl' => $sDavServer.'/addressbooks/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME,
			'SharedWithAllUrl' => $sDavServer.'/addressbooks/'.\Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME,
			'TeamAddressBookUrl' => $sDavServer.'/gab'
		);
	}	
	
}
