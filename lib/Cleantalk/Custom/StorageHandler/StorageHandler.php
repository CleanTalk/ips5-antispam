<?php

namespace Cleantalk\Custom\StorageHandler;

class StorageHandler implements \Cleantalk\Common\StorageHandler\StorageHandler
{

    public function getSetting($setting_name)
    {
        try {
            $setting_value = \IPS\Settings::i()->$setting_name;
        } catch (\Exception $e) {
            return null;
        }

        if ( ! is_string($setting_value) || $setting_value === '' ) {
            return is_string($setting_value) ? null : $setting_value;
        }

        $decoded = json_decode($setting_value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    public function deleteSetting($setting_name)
    {
        // An empty string makes IPS reset the setting to its default value.
        \IPS\Settings::i()->changeValues([$setting_name => '']);

        return true;
    }

    public function saveSetting($setting_name, $setting_value)
    {
        // IPS settings are plain strings, so arrays/objects have to be serialized.
        \IPS\Settings::i()->changeValues([$setting_name => json_encode($setting_value)]);

        return true;
    }

    public static function getUpdatingFolder()
    {
        return \IPS\Application::getRootPath() . '/uploads';
    }

    public static function getJsLocation()
    {
        // TODO: Implement getJsLocation() method.
    }
}
