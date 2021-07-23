<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\DavContacts;

use \Aurora\Modules\Contacts\Enums\StorageType;
use \Aurora\Modules\Contacts\Models\Contact;
use \Aurora\Modules\Contacts\Models\Group;

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
	}

	/**
	 *
	 * @param type $sUID
	 */
	protected function getContact($iUserId, $sStorage, $sUID)
	{
		return Contact::where('IdUser', $iUserId)->where('Storage', $sStorage)->where('Properties->' . self::GetName() . '::UID', $sUID)->first();
	}

	/**
	 *
	 * @param type $sUID
	 */
	protected function getGroup($iUserId, $sUID)
	{
		return Group::where('IdUser', $iUserId)->where('Properties->' . self::GetName() . '::UID', $sUID)->first();
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

			if ($oContact instanceof Contact)
			{
				$oContact->Auto = $bIsAuto;
				$oContact->setExtendedProp(self::GetName() . '::UID', $UID);
				$oContact->setExtendedProp(self::GetName() . '::VCardUID', \str_replace('urn:uuid:', '', (string) $oVCard->UID));
				$oContact->save();
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
			$aGroupData['Contacts'] = Contact::whereIn('Properties->DavContacts::VCardUID', $aGroupData['Contacts'])
				->get('UUID')
				->map(function ($oContact) {
					return $oContact->UUID;
				});
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
			$aGroupData['Contacts'] = Contact::whereIn('Properties->DavContacts::VCardUID', $aGroupData['Contacts'])
				->get('UUID')
				->map(function ($oContact) {
					return $oContact->UUID;
				})->toArray();
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
				if ($oContact instanceof \Aurora\Modules\Contacts\Models\Contact)
				{
					$oContact->setExtendedProp(self::GetName() . '::UID', $sUUID);
					$oContact->setExtendedProp(self::GetName() . '::VCardUID', $sUUID);
					$oContact->save();
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
				if ($oContact instanceof \Aurora\Modules\Contacts\Models\Contact)
				{
					$oDavContact = $this->getManager()->getContactById(
						$UserId,
						$oContact->{self::GetName() . '::UID'},
						$this->getStorage($aArgs['Contact']['Storage'])
					);

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
								$oGroup = \Aurora\Modules\Contacts\Module::Decorator()->GetGroup($UserId, $oGroupsContact->GroupUUID);
								if ($oGroup instanceof \Aurora\Modules\Contacts\Models\Group)
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
			$aEntities = Contact::whereIn('UUID', \array_unique($aArgs['UUIDs']))->get();

			$aUIDs = [];
			$sStorage = StorageType::Personal;
			foreach ($aEntities as $oContact)
			{
				if (\Aurora\Modules\Contacts\Module::Decorator()->CheckAccessToObject($oUser, $oContact))
				{
					$aUIDs[] = $oContact->{'DavContacts::UID'};
					$sStorage = $oContact->Storage; // TODO: sash04ek
				}
			}
			if ($sStorage !== StorageType::Team)
			{
				if (!$this->getManager()->deleteContacts(
						$aArgs['UserId'],
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
			$oGroup = \Aurora\Modules\Contacts\Module::getInstance()->GetGroup($aArgs['UserId'], $sUUID);
			if ($oGroup instanceof \Aurora\Modules\Contacts\Models\Group)
			{
				$oGroup->setExtendedProp(self::GetName() . '::UID', $sUUID);
				$oGroup->save();
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
			if ($oGroup instanceof \Aurora\Modules\Contacts\Models\Group)
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

		if ($oGroup instanceof \Aurora\Modules\Contacts\Models\Group)
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

		if ($oUser instanceof \Aurora\Modules\Core\Models\User && $oAuthenticatedUser->Role === \Aurora\System\Enums\UserRole::TenantAdmin && $oUser->IdTenant === $oAuthenticatedUser->IdTenant)
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
		if ($oContact instanceof \Aurora\Modules\Contacts\Models\Contact)
		{
			$mResult = $this->getManager()->getVCardObjectById($oContact->IdUser, $oContact->{'DavContacts::UID'}, $this->getStorage($oContact->Storage));

			return true;
		}
	}

}
