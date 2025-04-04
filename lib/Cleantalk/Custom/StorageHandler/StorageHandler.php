<?php

namespace Cleantalk\Custom\StorageHandler;

class StorageHandler implements \Cleantalk\Common\StorageHandler\StorageHandler
{

    public function getSetting($setting_name)
    {
        try {
            $setting_value = \IPS\Settings::i()->$setting_name;
        } catch (\Exception $e) {
            $setting_value = null;
        }
        return $setting_value;
    }

    public function deleteSetting($setting_name)
    {
        $this->saveSetting($setting_name, '');
    }

    public function saveSetting($setting_name, $setting_value)
    {
        \IPS\Settings::i()->changeValues([$setting_name => $setting_value]);
    }

    public static function getUpdatingFolder()
    {
        // TODO: Implement getUpdatingFolder() method.
    }

    public static function getJsLocation()
    {
        // TODO: Implement getJsLocation() method.
    }
}
