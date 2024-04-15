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

    public function init()
    {
        $this->subscribeEvent('MobileSync::GetInfo', array($this, 'onGetMobileSyncInfo'));
    }

    public function onGetMobileSyncInfo($aArgs, &$mResult)
    {
        $oDavModule = \Aurora\Modules\Dav\Module::Decorator();

        $sDavServer = $oDavModule->GetServerUrl();
        $aAddressBooks = \Aurora\Modules\Contacts\Module::Decorator()->GetStorages();

        $mResult['Dav']['Contacts'] = array();
        if (is_array($aAddressBooks) && count($aAddressBooks) > 0) {
            foreach ($aAddressBooks as $oBook) {
                $mResult['Dav']['Contacts'][] = array(
                    'Name' => isset($oBook['DisplayName']) ? $oBook['DisplayName'] : '',
                    'Url' => isset($oBook['Url']) ? rtrim($sDavServer . $oBook['Url'], '/') . '/' : ''
                );
            }
        }
    }
}
