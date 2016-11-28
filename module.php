<?php

class DavContactsModule extends AApiModule
{
	public $oApiContactsManager = null;

	protected $aRequireModules = array(
		'Contacts'
	);

	public function init() 
	{
		$this->oApiContactsManager = $this->GetManager('');
		
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
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onAfterCreateContact(&$aArgs, &$aResult)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		$sUUID = isset($aResult) ? $aResult : false;
		if ($sUUID)
		{
			$oContact = \CApi::GetModuleDecorator('Contacts')->GetContact($sUUID);
			if ($oContact instanceof \CContact)
			{
				if (!$this->oApiContactsManager->createContact($oContact))
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
	public function onAfterUpdateContact(&$aArgs, &$aResult)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if($aResult && is_array($aArgs['Contact']) && isset($aArgs['Contact']['UUID']))
		{
			$oContact = \CApi::GetModuleDecorator('Contacts')->GetContact($aArgs['Contact']['UUID']);
			if ($oContact instanceof \CContact)
			{
				if (!$this->oApiContactsManager->updateContact($oContact))
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
	public function onAfterDeleteContacts(&$aArgs, &$aResult)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);

		if ($aResult)
		{
			if (!$this->oApiContactsManager->deleteContacts(
				\CApi::getAuthenticatedUserId(),
				$aArgs['ContactUUIDs'])
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
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
	}
	
	/**
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onAfterUpdateGroup(&$aArgs, &$aResult)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
	}	
	
	/**
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onDeleteGroup(&$aArgs, &$aResult)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
	}
	
	/**
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onAfterAddContactsToGroup(&$aArgs, &$aResult)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
	}
	
	/**
	 * @param array $aArgs
	 * @param array $aResult
	 */
	public function onAfterRemoveContactsFromGroup(&$aArgs, &$aResult)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
	}	
}