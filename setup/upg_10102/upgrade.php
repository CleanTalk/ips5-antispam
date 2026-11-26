<?php

namespace IPS\antispambycleantalk\setup\upg_10102;

use IPS\Application;
use IPS\Db;
use IPS\Settings;

if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) ) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

class Upgrade
{
    public function step1(): bool
    {
        if (
            (int) Application::load('antispambycleantalk')->long_version === 10101 &&
            Settings::i()->ct_moderate_new == 1
        ) {
            // IPS imports the setting defaults after running custom upgrade steps.
            Db::i()->insert('core_sys_conf_settings', [
                'conf_key' => 'ct_check_comments',
                'conf_value' => '1',
                'conf_default' => '0',
                'conf_app' => 'antispambycleantalk',
                'conf_report' => null,
            ], true);
        }

        return true;
    }
}
