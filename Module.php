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
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @property Settings $oModuleSettings
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

    /**
     * @return Module
     */
    public static function getInstance()
    {
        return parent::getInstance();
    }

    /**
     * @return Module
     */
    public static function Decorator()
    {
        return parent::Decorator();
    }

    /**
     * @return Settings
     */
    public function getModuleSettings()
    {
        return $this->oModuleSettings;
    }

    /**
     * @return Manager
     */
    public function getManager()
    {
        if ($this->oManager === null) {
            $this->oManager = new Manager($this);
        }

        return $this->oManager;
    }

    public function init()
    {
        $this->subscribeEvent('MobileSync::GetInfo', array($this, 'onGetMobileSyncInfo'));
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
}
