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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 * 
 * @package Modules
 */

/**
 * @ignore
 * @package Contactsmain
 * @subpackage Helpers
 */
class CApiContactsVCardHelper
{
	
	public static function GetContactDataFromVcard($oVCard, $sUUID = '')
	{
		$aContact = [];
		
		if (!empty($sUUID))
		{
			$aContact['UUID'] = (string) $sUUID;
		}
		elseif (isset($oVCard->UID))
		{
			$aContact['UUID'] = (string) $oVCard->UID;
		}

		if (isset($oVCard->CATEGORIES))
		{
			$aGroupNames = $oVCard->CATEGORIES->getParts();
			if (is_array($aGroupNames) && count($aGroupNames) > 0)
			{
				$aContact['GroupNames'] = $aGroupNames;
			}
		}

		$aContact['FullName'] = isset($oVCard->FN) ? (string) $oVCard->FN : '';

		if (isset($oVCard->N))
		{
			$aNames = $oVCard->N->getParts();
			if (count($aNames) >= 2)
			{
				$aContact['LastName'] = !empty($aNames[0]) ? (string) $aNames[0] : '';
				$aContact['FirstName'] = !empty($aNames[1]) ? (string) $aNames[1] : '';
			}
		}

		$aContact['NickName'] = isset($oVCard->NICKNAME) ? (string) $oVCard->NICKNAME : '';
		$aContact['Notes'] = isset($oVCard->NOTE) ? (string) $oVCard->NOTE : '';

		if (isset($oVCard->BDAY))
		{
			$aDateTime = explode('T', (string) $oVCard->BDAY);
			if (isset($aDateTime[0]))
			{
				$aDate = explode('-', $aDateTime[0]);
				if (count($aDate) >= 3)
				{
					$aContact['BirthYear'] = (int) $aDate[0];
					$aContact['BirthMonth'] = (int) $aDate[1];
					$aContact['BirthDay'] = (int) $aDate[2];
				}
			}
		}

		if (isset($oVCard->ORG))
		{
			$aOrgs = $oVCard->ORG->getParts();

			if (count($aOrgs) >= 2)
			{
				$aContact['BusinessCompany'] = !empty($aOrgs[0]) ? (string) $aOrgs[0] : '';
				$aContact['BusinessDepartment'] = !empty($aOrgs[1]) ? (string) $aOrgs[1] : '';
			}
		}

		$aContact['BusinessJobTitle'] = isset($oVCard->TITLE) ? (string) $oVCard->TITLE : '';

		if (isset($oVCard->ADR))
		{
			foreach($oVCard->ADR as $oAdr)
			{
				$aAdrs = $oAdr->getParts();
				$oTypes = $oAdr['TYPE'];
				if ($oTypes)
				{
					if ($oTypes->has('WORK'))
					{
						$aContact['BusinessAddress'] = isset($aAdrs[2]) ? (string) $aAdrs[2] : '';
						$aContact['BusinessCity'] = isset($aAdrs[3]) ? (string) $aAdrs[3] : '';
						$aContact['BusinessState'] = isset($aAdrs[4]) ? (string) $aAdrs[4] : '';
						$aContact['BusinessZip'] = isset($aAdrs[5]) ? (string) $aAdrs[5] : '';
						$aContact['BusinessCountry'] = isset($aAdrs[6]) ? (string) $aAdrs[6] : '';
					}
					if ($oTypes->has('HOME'))
					{
						$aContact['PersonalAddress'] = isset($aAdrs[2]) ? (string) $aAdrs[2] : '';
						$aContact['PersonalCity'] = isset($aAdrs[3]) ? (string) $aAdrs[3] : '';
						$aContact['PersonalState'] = isset($aAdrs[4]) ? (string) $aAdrs[4] : '';
						$aContact['PersonalZip'] = isset($aAdrs[5]) ? (string) $aAdrs[5] : '';
						$aContact['PersonalCountry'] = isset($aAdrs[6]) ? (string) $aAdrs[6] : '';
					}
				}
			}
		}

		if (isset($oVCard->EMAIL))
		{
			foreach($oVCard->EMAIL as $oEmail)
			{
				$oType = $oEmail['TYPE'];
				if ($oType)
				{
					if ($oType->has('WORK') || $oType->has('INTERNET'))
					{
						$aContact['BusinessEmail'] = (string) $oEmail;
						if ($oType->has('PREF'))
						{
							$aContact['PrimaryEmail'] = EContactsPrimaryEmail::Business;
						}
					}
					else if ($oType->has('HOME'))
					{
						$aContact['PersonalEmail'] = (string) $oEmail;
						if ($oType->has('PREF'))
						{
							$aContact['PrimaryEmail'] = EContactsPrimaryEmail::Personal;
						}
					}
					else if ($oType->has('OTHER'))
					{
						$aContact['OtherEmail'] = (string) $oEmail;
						if ($oType->has('PREF'))
						{
							$aContact['PrimaryEmail'] = EContactsPrimaryEmail::Other;
						}
					}
					else if ($oEmail->group && isset($oVCard->{$oEmail->group.'.X-ABLABEL'}) &&
						strtolower((string) $oVCard->{$oEmail->group.'.X-ABLABEL'}) === '_$!<other>!$_')
					{
						$aContact['OtherEmail'] = (string) $oEmail;
						if ($oType->has('PREF'))
						{
							$aContact['PrimaryEmail'] = EContactsPrimaryEmail::Other;
						}
					}
				}
			}
		}

		if (isset($oVCard->URL))
		{
			foreach($oVCard->URL as $oUrl)
			{
				$oTypes = $oUrl['TYPE'];
				if ($oTypes)
				{
					if ($oTypes->has('HOME'))
					{
						$aContact['PersonalWeb'] = (string) $oUrl;
					}
					else if ($oTypes->has('WORK'))
					{
						$aContact['BusinessWeb'] = (string) $oUrl;
					}
				}
			}
		}

		if (isset($oVCard->TEL))
		{
			foreach($oVCard->TEL as $oTel)
			{
				$oTypes = $oTel['TYPE'];
				if ($oTypes)
				{
					if ($oTypes->has('FAX'))
					{
						if ($oTypes->has('HOME'))
						{
							$aContact['PersonalFax'] = (string) $oTel;
						}
						if ($oTypes->has('WORK'))
						{
							$aContact['BusinessFax'] = (string) $oTel;
						}
					}
					else
					{
						if ($oTypes->has('CELL'))
						{
							$aContact['PersonalMobile'] = (string) $oTel;
						}
						else if ($oTypes->has('HOME'))
						{
							$aContact['PersonalPhone'] = (string) $oTel;
						}
						else if ($oTypes->has('WORK'))
						{
							$aContact['BusinessPhone'] = (string) $oTel;
						}
					}
				}
			}
		}

		if (isset($oVCard->{'X-AFTERLOGIC-OFFICE'}))
		{
			$aContact['BusinessOffice'] = (string) $oVCard->{'X-AFTERLOGIC-OFFICE'};
		}

		if (isset($oVCard->{'X-AFTERLOGIC-USE-FRIENDLY-NAME'}))
		{
			$aContact['UseFriendlyName'] = '1' === (string) $oVCard->{'X-AFTERLOGIC-USE-FRIENDLY-NAME'};
		}
		
		return $aContact;
	}
	
	/**
	* @param CContact $oContact
	* @param \Sabre\VObject\Component $oVCard
	* @return void
	*/
	public static function UpdateVCardAddressesFromContact($oContact, &$oVCard)
	{
		$bFindHome = false;
		$bFindWork = false;

		$oVCardCopy = clone $oVCard;

		$sADRHome = array(
			'',
			'',
			$oContact->PersonalAddress,
			$oContact->PersonalCity,
			$oContact->PersonalState,
			$oContact->PersonalZip,
			$oContact->PersonalCountry
		);

		if (empty($oContact->PersonalAddress) && empty($oContact->PersonalCity) &&
				empty($oContact->PersonalState) && empty($oContact->PersonalZip) &&
						empty($oContact->PersonalCountry))
		{
			$bFindHome = true;
		}

		$sADRWork = array(
			'',
			'',
			$oContact->BusinessAddress,
			$oContact->BusinessCity,
			$oContact->BusinessState,
			$oContact->BusinessZip,
			$oContact->BusinessCountry
		);

		if (empty($oContact->BusinessAddress) && empty($oContact->BusinessCity) &&
				empty($oContact->BusinessState) && empty($oContact->BusinessZip) &&
						empty($oContact->BusinessCountry))
		{
			$bFindWork = true;
		}

		if (isset($oVCardCopy->ADR))
		{
			unset($oVCard->ADR);
			foreach ($oVCardCopy->ADR as $oAdr)
			{
				if ($oTypes = $oAdr['TYPE'])
				{
					if ($oTypes->has('HOME'))
					{
						if ($bFindHome)
						{
							unset($oAdr);
						}
						else
						{
							$oAdr->setValue($sADRHome);
							$bFindHome = true;
						}
					}
					if ($oTypes->has('WORK'))
					{
						if ($bFindWork)
						{
							unset($oAdr);
						}
						else
						{
							$oAdr->setValue($sADRWork);
							$bFindWork = true;
						}
					}
				}
				if (isset($oAdr))
				{
					$oVCard->add($oAdr);
				}
			}
		}

		if (!$bFindHome)
		{
			$oVCard->add('ADR', $sADRHome, array('TYPE' => array('HOME')));
		}
		if (!$bFindWork)
		{
			$oVCard->add('ADR', $sADRWork, array('TYPE' => array('WORK')));
		}
	}

	/**
	* @param CContact $oContact
	* @param \Sabre\VObject\Component\VCard $oVCard
	* @return void
	*/
	public static function UpdateVCardEmailsFromContact($oContact, &$oVCard)
	{
		$bFindHome = (empty($oContact->PersonalEmail)) ? false : true;
		$bFindWork = (empty($oContact->BusinessEmail)) ? false : true;
		$bFindOther = (empty($oContact->OtherEmail)) ? false : true;

		$oVCardCopy = clone $oVCard;

		if (isset($oVCardCopy->EMAIL))
		{
			unset($oVCard->EMAIL);
			foreach ($oVCardCopy->EMAIL as $oEmail)
			{
				if ($oTypes = $oEmail['TYPE'])
				{
					$aTypes = array();
					foreach ($oTypes as $sType)
					{
						if ('PREF' !== strtoupper($sType))
						{
							$aTypes[] = $sType;
						}
					}
					$oTypes->setValue($aTypes);

					if ($oTypes->has('HOME'))
					{
						if (!$bFindHome)
						{
							unset($oEmail);
						}
						else
						{
							$bFindHome = false;
							if ($oContact->PrimaryEmail == EContactsPrimaryEmail::Personal)
							{
								$oTypes->addValue('PREF');
							}
							$oEmail->setValue($oContact->PersonalEmail);
						}
					}
					else if ($oTypes->has('WORK'))
					{
						if (!$bFindWork)
						{
							unset($oEmail);
						}
						else
						{
							$bFindWork = false;
							if ($oContact->PrimaryEmail == EContactsPrimaryEmail::Business)
							{
								$oTypes->addValue('PREF');
							}
							$oEmail->setValue($oContact->BusinessEmail);
						}
					}
					else if ($oTypes->has('OTHER'))
					{
						if (!$bFindOther)
						{
							unset($oEmail);
						}
						else
						{
							$bFindOther = false;
							if ($oContact->PrimaryEmail == EContactsPrimaryEmail::Other)
							{
								$oTypes->addValue('PREF');
							}
							$oEmail->setValue($oContact->OtherEmail);
						}
					}
					else if ($oEmail->group && isset($oVCardCopy->{$oEmail->group.'.X-ABLabel'}) &&
							(strtolower((string) $oVCardCopy->{$oEmail->group.'.X-ABLabel'}) === '_$!<other>!$_') ||
							(strtolower((string) $oVCardCopy->{$oEmail->group.'.X-ABLabel'}) === 'other'))
					{
						if (!$bFindOther)
						{
							unset($oVCardCopy->{$oEmail->group.'.X-ABLabel'});
							unset($oEmail);
						}
						else
						{
							$bFindOther = false;
							if ($oContact->PrimaryEmail == EContactsPrimaryEmail::Other)
							{
								$oTypes->addValue('PREF');
							}
							$oEmail->setValue($oContact->OtherEmail);
						}
					}
						
				}
				if (isset($oEmail))
				{
					$oVCard->add($oEmail);
				}
			}
		}
		
		if ($bFindHome)
		{
			$aTypes = array('HOME');
			if ($oContact->PrimaryEmail == EContactsPrimaryEmail::Personal)
			{
				$aTypes[] = 'PREF';
			}
			$oEmail = $oVCard->add('EMAIL', $oContact->PersonalEmail, array('TYPE' => $aTypes));
		}
		if ($bFindWork)
		{
			$aTypes = array('WORK');
			if ($oContact->PrimaryEmail == EContactsPrimaryEmail::Business)
			{
				$aTypes[] = 'PREF';
			}
			$oEmail = $oVCard->add('EMAIL', $oContact->BusinessEmail, array('TYPE' => $aTypes));
		}
		if ($bFindOther)
		{
			$aTypes = array('OTHER');
			if ($oContact->PrimaryEmail == EContactsPrimaryEmail::Other)
			{
				$aTypes[] = 'PREF';
			}			
			$oEmail = $oVCard->add('EMAIL', $oContact->OtherEmail, array('TYPE' => $aTypes));
		}
	}

	/**
	* @param CContact $oContact
	* @param \Sabre\VObject\Component $oVCard
	* @return void
	*/
	public static function UpdateVCardUrlsFromContact($oContact, &$oVCard)
	{
		$bFindHome = false;
		$bFindWork = false;

		if (empty($oContact->PersonalWeb))
		{
			$bFindHome = true;
		}
		if (empty($oContact->BusinessWeb))
		{
			$bFindWork = true;
		}

		if (isset($oVCard->URL))
		{
			foreach ($oVCard->URL as $oUrl)
			{
				if ($oTypes = $oUrl['TYPE'])
				{
					if ($oTypes->has('HOME'))
					{
						if ($bFindHome)
						{
							unset($oUrl);
						}
						else
						{
							$oUrl->setValue($oContact->PersonalWeb);
							$bFindHome = true;
						}
					}
					if ($oTypes->has('WORK'))
					{
						if ($bFindWork)
						{
							unset($oUrl);
						}
						else
						{
							$oUrl->setValue($oContact->BusinessWeb);
							$bFindWork = true;
						}
					}
				}
			}
		}

		if (!$bFindHome)
		{
			$oVCard->add('URL', $oContact->PersonalWeb, array('TYPE' => array('HOME')));
		}
		if (!$bFindWork)
		{
			$oVCard->add('URL', $oContact->BusinessWeb, array('TYPE' => array('WORK')));
		}
	}

	/**
	* @param CContact $oContact
	* @param \Sabre\VObject\Component\VCard $oVCard
	* @return void
	*/
	public static function UpdateVCardPhonesFromContact($oContact, &$oVCard)
	{
		$bFindHome = false;
		$bFindWork = false;
		$bFindCell = false;
		$bFindPersonalFax = false;
		$bFindWorkFax = false;

		$oVCardCopy = clone $oVCard;

		if (empty($oContact->PersonalPhone))
		{
			$bFindHome = true;
		}
		if (empty($oContact->BusinessPhone))
		{
			$bFindWork = true;
		}
		if (empty($oContact->PersonalMobile))
		{
			$bFindCell = true;
		}
		if (empty($oContact->PersonalFax))
		{
			$bFindPersonalFax = true;
		}
		if (empty($oContact->BusinessFax))
		{
			$bFindWorkFax = true;
		}

		if (isset($oVCardCopy->TEL))
		{
			unset($oVCard->TEL);
			foreach ($oVCardCopy->TEL as $oTel)
			{
				if ($oTypes = $oTel['TYPE'])
				{
					if ($oTypes->has('VOICE'))
					{
						if ($oTypes->has('HOME'))
						{
							if ($bFindHome)
							{
								unset($oTel);
							}
							else
							{
								$oTel->setValue($oContact->PersonalPhone);
								$bFindHome = true;
							}
						}
						if ($oTypes->has('WORK'))
						{
							if ($bFindWork)
							{
								unset($oTel);
							}
							else
							{
								$oTel->setValue($oContact->BusinessPhone);
								$bFindWork = true;
							}
						}
						if ($oTypes->has('CELL'))
						{
							if ($bFindCell)
							{
								unset($oTel);
							}
							else
							{
								$oTel->setValue($oContact->PersonalMobile);
								$bFindCell = true;
							}
						}
					}
					else if ($oTypes->has('FAX'))
					{
						if ($oTypes->has('HOME'))
						{
							if ($bFindPersonalFax)
							{
								unset($oTel);
							}
							else
							{
								$oTel->setValue($oContact->PersonalFax);
								$bFindPersonalFax = true;
							}
						}
						if ($oTypes->has('WORK'))
						{
							if ($bFindWorkFax)
							{
								unset($oTel);
							}
							else
							{
								$oTel->setValue($oContact->BusinessFax);
								$bFindWorkFax = true;
							}
						}
					}
				}
				if (isset($oTel))
				{
					$oVCard->add($oTel);
				}
			}
		}

		if (!$bFindHome)
		{
			$oVCard->add('TEL', $oContact->PersonalPhone, array('TYPE' => array('VOICE', 'HOME')));
		}
		if (!$bFindWork)
		{
			$oVCard->add('TEL', $oContact->BusinessPhone, array('TYPE' => array('VOICE', 'WORK')));
		}
		if (!$bFindCell)
		{
			$oVCard->add('TEL', $oContact->PersonalMobile, array('TYPE' => array('VOICE', 'CELL')));
		}
		if (!$bFindPersonalFax)
		{
			$oVCard->add('TEL', $oContact->PersonalFax, array('TYPE' => array('FAX', 'HOME')));
		}
		if (!$bFindWorkFax)
		{
			$oVCard->add('TEL', $oContact->BusinessFax, array('TYPE' => array('FAX', 'WORK')));
		}
	}

	/**
	* @param CContact $oContact
	* @param \Sabre\VObject\Component $oVCard
	* @param bool $bIsUpdate = false
	* @return void
	*/
	public static function UpdateVCardFromContact($oContact, &$oVCard, $bIsUpdate = false)
	{
		$oVCard->VERSION = '3.0';
		$oVCard->PRODID = '-//Afterlogic//7.5.x//EN';

		$oVCard->UID = $oContact->UUID;

		$oVCard->FN = $oContact->FullName;
		$oVCard->N = array(
			$oContact->LastName,
			$oContact->FirstName,
			'',
			$oContact->Title,
			'',
			''
		);
		$oVCard->{'X-AFTERLOGIC-OFFICE'} = $oContact->BusinessOffice;
		$oVCard->{'X-AFTERLOGIC-USE-FRIENDLY-NAME'} = $oContact->UseFriendlyName ? '1' : '0';
		$oVCard->TITLE = $oContact->BusinessJobTitle;
		$oVCard->NICKNAME = $oContact->NickName;
		$oVCard->NOTE = $oContact->Notes;
		$oVCard->ORG = array(
			$oContact->BusinessCompany,
			$oContact->BusinessDepartment
		);
		$oVCard->CATEGORIES = $oContact->GroupUUIDs;

		self::UpdateVCardAddressesFromContact($oContact, $oVCard);
		self::UpdateVCardEmailsFromContact($oContact, $oVCard);
		self::UpdateVCardUrlsFromContact($oContact, $oVCard);
		self::UpdateVCardPhonesFromContact($oContact, $oVCard);

		unset($oVCard->BDAY);
		if ($oContact->BirthYear !== 0 && $oContact->BirthMonth !== 0 && $oContact->BirthDay !== 0)
		{
			$sBDayDT = $oContact->BirthYear.'-'.$oContact->BirthMonth.'-'.$oContact->BirthDay;
			$oVCard->add('BDAY', $sBDayDT);
		}
	}
}
