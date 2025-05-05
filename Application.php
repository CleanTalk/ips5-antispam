<?php
/**
 * @brief		Antispam by Cleantalk Application Class
 * @author		<a href='https://cleantalk.org'>CleanTalk team</a>
 * @copyright	(c) 2020 CleanTalk team
 * @package		Invision Community
 * @subpackage	Antispam by Cleantalk
 * @since		26 Oct 2020
 * @version
 */

namespace IPS\antispambycleantalk;

require_once(\IPS\Application::getRootPath().'/applications/antispambycleantalk/lib/autoload.php');

use Cleantalk\Common\Antispam\Cleantalk;
use Cleantalk\Common\Antispam\CleantalkRequest;
use Cleantalk\Common\Firewall\Firewall;
use Cleantalk\Custom\Db\Db;
use Cleantalk\Custom\Helper\Helper;
use Cleantalk\Custom\Helper\Helper as CleantalkHelper;

define('APBCT_TBL_FIREWALL_DATA', 'cleantalk_sfw');      // Table with firewall data.
define('APBCT_TBL_FIREWALL_LOG',  'cleantalk_sfw_logs'); // Table with firewall logs.
define('APBCT_TBL_AC_LOG',        'cleantalk_ac_log');   // Table with firewall logs.
define('APBCT_TBL_AC_UA_BL',      'cleantalk_ua_bl');    // Table with User-Agents blacklist.
define('APBCT_TBL_SESSIONS',      'cleantalk_sessions'); // Table with session data.
define('APBCT_SPAMSCAN_LOGS',     'cleantalk_spamscan_logs'); // Table with session data.
define('APBCT_SELECT_LIMIT',      5000); // Select limit for logs.
define('APBCT_WRITE_LIMIT',       5000); // Write limit for firewall data.

/**
 * Antispam by Cleantalk Application Class
 */
class Application extends \IPS\Application
{
    public function installOther() {

        // Show admin notification about empty key
        $coreApp = \IPS\Application::load('core');
        if( \version_compare( $coreApp->version, '4.4.0') >= 0 ) {
            if( \IPS\Application::appIsEnabled('antispambycleantalk') && ! \IPS\Settings::i()->ct_access_key ) {
                \IPS\core\AdminNotification::send( 'antispambycleantalk', 'Notification', 'keyIsEmpty', true );
            }
        }

    }
	public static function apbct_sfw_update($access_key = '') {
	    if( empty( $access_key ) ){
        	$access_key = \IPS\Settings::i()->ct_access_key;
        	if (empty($access_key)) {
        		return false;
        	}
	    }
        $firewall = new Firewall(
            $access_key,
            APBCT_TBL_FIREWALL_LOG
        );
        $fw_updater = $firewall->getUpdater();
        $fw_updater->update();

        return true;
	}
	public static function apbct_sfw_send_logs($access_key = '') {
	    if( empty( $access_key ) ){
        	$access_key = \IPS\Settings::i()->ct_access_key;
        	if (empty($access_key)) {
        		return false;
        	}
	    }

        $firewall = new Firewall( $access_key, Db::getInstance(), APBCT_TBL_FIREWALL_LOG );
		$firewall->setSpecificHelper( new CleantalkHelper() );
        $result = $firewall->sendLogs();

        return true;
	}
    /**
     * @see \IPS\Application::privacyPolicyThirdParties()
     */
    public function privacyPolicyThirdParties() : array
    {
        return array(
            array(
                'title' => \IPS\Member::loggedIn()->language()->addToStack('__app_antispambycleantalk'),
                'description' => \IPS\Member::loggedIn()->language()->addToStack('antispambycleantalk_privacy_description'),
                'privacyUrl' => 'https://cleantalk.org/publicoffer',
            )
        );
    }

    public static function getAntispamModuleVersion()
    {
        $application = \IPS\Application::load('antispambycleantalk');
        return $application->version;
    }

    public static function spamCheck($request_params, $reg_flag = false)
    {
        $ct_access_key = \IPS\Settings::i()->ct_access_key;

        if ( ! $ct_access_key ) {
            return false;
        }

        $lang = \IPS\Lang::getEnabledLanguages();
        $locale = $lang[\IPS\Lang::defaultLanguage()]->short;

        // Pointer data
        $pointer_data = (isset($_COOKIE['ct_pointer_data']) ? json_decode($_COOKIE['ct_pointer_data']) : 0);
        // Timezone from JS
        $js_timezone = (isset($_COOKIE['ct_timezone']) ? $_COOKIE['ct_timezone'] : 0);
        //First key down timestamp
        $first_key_press_timestamp = isset($_COOKIE['ct_fkp_timestamp']) ? $_COOKIE['ct_fkp_timestamp'] : 0;
        // Page opened timestamp
        $page_set_timestamp = (isset($_COOKIE['ct_ps_timestamp']) ? $_COOKIE['ct_ps_timestamp'] : 0);

        $ct = new Cleantalk();
        $ct->server_url = \IPS\Settings::i()->ct_server_url;
        $ct->work_url = \IPS\Settings::i()->ct_work_url;
        $ct->server_ttl = \IPS\Settings::i()->ct_server_ttl;
        $ct->server_changed = \IPS\Settings::i()->ct_server_changed;

        $default_sender_info = [
            'cms_lang' => $locale,
            'REFFERRER' => $_SERVER['HTTP_REFERER'],
            'USER_AGENT' => $_SERVER['HTTP_USER_AGENT'],
            'mouse_cursor_positions' => $pointer_data,
            'js_timezone' => $js_timezone,
            'key_press_timestamp' => $first_key_press_timestamp,
            'page_set_timestamp' => $page_set_timestamp,
            'REFFERRER_PREVIOUS' => isset($_COOKIE['ct_prev_referer']) ? $_COOKIE['ct_prev_referer'] : null,
            'cookies_enabled' => self::ctCookiesTest(),
            'site_url' => $_SERVER['HTTP_HOST'],
        ];
        $sender_info = isset($request_params['sender_info'])
            ? array_merge($default_sender_info, $request_params['sender_info'])
            : $default_sender_info;

        $default_post_info = [
            // @ToDo add default values if needed
        ];
        $post_info = isset($request_params['post_info'])
            ? array_merge($default_post_info, $request_params['post_info'])
            : $default_post_info;

        $default_request_params = [
            'auth_key' => $ct_access_key,
            'js_on' => isset($_COOKIE['ct_checkjs']) && in_array($_COOKIE['ct_checkjs'], self::getCheckJSArray()) ? 1 : 0,
            'sender_ip' => Helper::ipGet('real', false),
            'x_forwarded_for' => Helper::ipGet('x_forwarded_for', false),
            'x_real_ip' => Helper::ipGet('x_real_ip', false),
            'sender_email' => '',
            'sender_nickname' => '',
            'agent' => 'ips5-' . self::getAntispamModuleVersion(),
        ];

        if ( isset($_COOKIE['ct_ps_timestamp']) ) {
            $default_request_params[] = time() - (int)$_COOKIE['ct_ps_timestamp'];
        }

        if ( isset($_POST['ct_bot_detector_event_token']) ) {
            $default_request_params['event_token'] = $_POST['ct_bot_detector_event_token'];
        }
        $request_params = array_merge($default_request_params, $request_params);

        $request_params['sender_info'] = $sender_info;
        $request_params['post_info'] = $post_info;

        $ct_request = new CleantalkRequest($request_params);

        $result = $reg_flag ? $ct->isAllowUser($ct_request) : $ct->isAllowMessage($ct_request);
        if ( $ct->server_change ) {
            \IPS\Settings::i()->ct_work_url = $ct->work_url;
            \IPS\Settings::i()->ct_server_ttl = $ct->server_ttl;
            \IPS\Settings::i()->ct_server_changed = time();
        }

        return $result;
    }

    private static function getCheckJSArray()
    {
        $result = array();

        for ( $i = -5; $i <= 1; $i++ ) {
            $result[] = md5(
                \IPS\Settings::i()->ct_access_key . '+' . \IPS\Settings::i()->email_in . date(
                    "Ymd",
                    time() + 86400 * $i
                )
            );
        }

        return $result;
    }

    private static function ctCookiesTest()
    {
        if ( isset($_COOKIE['ct_cookies_test']) ) {
            $cookie_test = json_decode(stripslashes($_COOKIE['ct_cookies_test']), true);

            $check_srting = trim(\IPS\Settings::i()->ct_access_key);
            foreach ( $cookie_test['cookies_names'] as $cookie_name ) {
                $check_srting .= isset($_COOKIE[$cookie_name]) ? $_COOKIE[$cookie_name] : '';
            }
            unset($cokie_name);

            if ( $cookie_test['check_value'] == md5($check_srting) ) {
                return 1;
            }

            return 0;
        }

        return null;
    }
}
