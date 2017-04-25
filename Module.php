<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 */

namespace Aurora\Modules\DavContacts;

/**
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	public $oApiContactsManager = null;

	protected $aRequireModules = array(
		'Contacts'
	);
	
	protected $__LOCK_AFTER_CREATE_CONTACT_SUBSCRIBE__ = false;
	protected $__LOCK_AFTER_UPDATE_CONTACT_SUBSCRIBE__ = false;

	public function init() 
	{
		$this->oApiContactsManager = $this->GetManager();
		
		$this->subscribeEvent('Contacts::CreateContact::after', array($this, 'onAfterCreateContact'));
		$this->subscribeEvent('Contacts::UpdateContact::after', array($this, 'onAfterUpdateContact'));
		$this->subscribeEvent('Contacts::DeleteContacts::after', array($this, 'onAfterDeleteContacts'));

		$this->subscribeEvent('Contacts::CreateGroup::after', array($this, 'onAfterCreateGroup'));
		$this->subscribeEvent('Contacts::UpdateGroup::after', array($this, 'onAfterUpdateGroup'));
		$this->subscribeEvent('Contacts::DeleteGroup::after', array($this, 'onAfterDeleteGroup'));

		$this->subscribeEvent('Contacts::AddContactsToGroup::after', array($this, 'onAfterAddContactsToGroup'));
		$this->subscribeEvent('Contacts::RemoveContactsFromGroup::after', array($this, 'onAfterRemoveContactsFromGroup'));
	}
	
	/**
	 * 
	 * @param int $iUserId
	 * @param string $VCard
	 * @return bool|string
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function CreateContact($UserId, $VCard, $UUID)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oVCard = \Sabre\VObject\Reader::read($VCard, \Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES);
		$aContactData = CApiContactsVCardHelper::GetContactDataFromVcard($oVCard, $UUID);
		
		$this->__LOCK_AFTER_CREATE_CONTACT_SUBSCRIBE__ = true;
		$mResult = \Aurora\System\Api::GetModuleDecorator('Contacts')->CreateContact($aContactData, $UserId);
		$this->__LOCK_AFTER_CREATE_CONTACT_SUBSCRIBE__ = false;
		
		return $mResult;
	}	
	
	/**
	 * 
	 * @param string $VCard
	 * @return bool|string
	 * @throws \Aurora\System\Exceptions\ApiException
	 */
	public function UpdateContact($VCard, $UUID)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		$oVCard = \Sabre\VObject\Reader::read($VCard, \Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES);
		$aContactData = CApiContactsVCardHelper::GetContactDataFromVcard($oVCard, $UUID);
		
		$this->__LOCK_AFTER_UPDATE_CONTACT_SUBSCRIBE__ = true;
		$mResult = \Aurora\System\Api::GetModuleDecorator('Contacts')->UpdateContact($aContactData);
		$this->__LOCK_AFTER_UPDATE_CONTACT_SUBSCRIBE__ = false;
		
		return $mResult;
	}	
	
	/**
	 * 
	 * @param type $UserId
	 * @param type $UUID
	 * @param type $Storage
	 * @param type $FileName
	 */
	public function SaveContactAsTempFile($UserId, $UUID, $Storage, $FileName)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);

		$mResult = false;

		$sVCardData = $this->oApiContactsManager->getVCardObjectById($UserId, $UUID);
		if ($sVCardData)
		{
			$sUUID = \Aurora\System\Api::getUserUUIDById($UserId);
			try
			{
				$sMimeType = 'text/vcard';
				$sTempName = md5($sUUID.$UUID);
				$oApiFileCache = \Aurora\System\Api::GetSystemManager('Filecache');

				if (!$oApiFileCache->isFileExists($sUUID, $sTempName))
				{
					$oApiFileCache->put($sUUID, $sTempName, $sVCardData);
				}

				if ($oApiFileCache->isFileExists($sUUID, $sTempName))
				{
					$mResult = \Aurora\System\Utils::GetClientFileResponse($UserId, $FileName, $sTempName, $oApiFileCache->fileSize($sUUID, $sTempName));
				}
			}
			catch (\Exception $oException)
			{
				throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::MailServerError, $oException);
			}
		}
		
		return $mResult;		
	}	

	/**
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onAfterCreateContact(&$aArgs, &$aResult)
	{
		if (!$this->__LOCK_AFTER_CREATE_CONTACT_SUBSCRIBE__)
		{
			\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
			$sUUID = isset($aResult) ? $aResult : false;
			if ($sUUID)
			{
				$oContact = \Aurora\System\Api::GetModuleDecorator('Contacts')->GetContact($sUUID);
				if ($oContact instanceof \CContact)
				{
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
			\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);

			if($aResult && is_array($aArgs['Contact']) && isset($aArgs['Contact']['UUID']))
			{
				$oContact = \Aurora\System\Api::GetModuleDecorator('Contacts')->GetContact($aArgs['Contact']['UUID']);
				if ($oContact instanceof \CContact)
				{
					$oDavContact = $this->oApiContactsManager->getContactById($aArgs['UserId'], $oContact->UUID.'.vcf');
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
	public function onAfterDeleteContacts(&$aArgs, &$aResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);

		if ($aResult && isset($aArgs['UUIDs']))
		{
			if (!$this->oApiContactsManager->deleteContacts(
				\Aurora\System\Api::getAuthenticatedUserId(),
				$aArgs['UUIDs'])
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
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
	}
	
	/**
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onAfterUpdateGroup(&$aArgs, &$aResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
	}	
	
	/**
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onDeleteGroup(&$aArgs, &$aResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
	}
	
	/**
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onAfterAddContactsToGroup(&$aArgs, &$aResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
	}
	
	/**
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onAfterRemoveContactsFromGroup(&$aArgs, &$aResult)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
	}	
}
