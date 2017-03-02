<?php

class DavContactsModule extends \Aurora\System\AbstractModule
{
	public $oApiContactsManager = null;

	protected $aRequireModules = array(
		'Contacts'
	);
	
	protected $__LOCK_AFTER_CREATE_CONTACT_SUBSCRIBE__ = false;
	protected $__LOCK_AFTER_UPDATE_CONTACT_SUBSCRIBE__ = false;

	public function init() 
	{
		$this->incClass('vcard-helper');
		
		$this->oApiContactsManager = $this->GetManager();
		
		$this->subscribeEvent('Contacts::GetImportExportFormats', array($this, 'onGetImportExportFormats'));
		$this->subscribeEvent('Contacts::GetExportOutput', array($this, 'onGetExportOutput'));
		$this->subscribeEvent('Contacts::Import', array($this, 'onImport'));
		
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
	 * @throws \System\Exceptions\ApiException
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
	 * @throws \System\Exceptions\ApiException
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

	public function onGetImportExportFormats(&$aFormats)
	{
		$aFormats[] = 'vcf';
	}
	
	public function onGetExportOutput($aArgs, &$sOutput)
	{
		if ($aArgs['Format'] === 'vcf')
		{
            $sOutput = '';
			if (is_array($aArgs['Contacts']))
			{
				foreach ($aArgs['Contacts'] as $oContact)
				{
					$oVCard = new \Sabre\VObject\Component\VCard();
					\CApiContactsVCardHelper::UpdateVCardFromContact($oContact, $oVCard);
					$sOutput .= $oVCard->serialize();
				}
			}
		}
	}
	
	public function onImport($aArgs, &$mImportResult)
	{
		if ($aArgs['Format'] === 'vcf')
		{
			$mImportResult['ParsedCount'] = 0;
			$mImportResult['ImportedCount'] = 0;
			// You can either pass a readable stream, or a string.
			$oHandler = fopen($aArgs['TempFileName'], 'r');
			$oSplitter = new \Sabre\VObject\Splitter\VCard($oHandler);
			$oContactsDecorator = \Aurora\System\Api::GetModuleDecorator('Contacts');
			$oApiContactsManager = $oContactsDecorator ? $oContactsDecorator->GetApiContactsManager() : null;
			if ($oApiContactsManager)
			{
				while ($oVCard = $oSplitter->getNext())
				{
					$aContactData = \CApiContactsVCardHelper::GetContactDataFromVcard($oVCard);
					$oContact = isset($aContactData['UUID']) ? $oApiContactsManager->getContact($aContactData['UUID']) : null;
					$mImportResult['ParsedCount']++;
					if (!isset($oContact) || empty($oContact))
					{
						if ($oContactsDecorator->CreateContact($aContactData, $aArgs['User']->EntityId))
						{
							$mImportResult['ImportedCount']++;
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
