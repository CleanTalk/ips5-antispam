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

use Cleantalk\ApbctIPS\Helper;
use Cleantalk\Common\Antispam\Cleantalk;
use Cleantalk\Common\Antispam\CleantalkRequest;
use Cleantalk\ApbctIPS\Helper as CleantalkHelper;
use Cleantalk\ApbctIPS\DB;
use Cleantalk\Common\Firewall\Firewall;
use IPS\Output;
use IPS\Request;

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
            if( ! \IPS\Settings::i()->ct_access_key ) {
                \IPS\core\AdminNotification::send( 'antispambycleantalk', 'Notification', 'keyIsEmpty', true );
            }
        }

    }
	static public function apbct_sfw_update($access_key = '') {
	    if( empty( $access_key ) ){
        	$access_key = \IPS\Settings::i()->ct_access_key;
        	if (empty($access_key)) {
        		return false;
        	}
	    }
        $firewall = new Firewall(
            $access_key,
            DB::getInstance(),
            APBCT_TBL_FIREWALL_LOG
        );
        $firewall->setSpecificHelper( new CleantalkHelper() );
        $fw_updater = $firewall->getUpdater( APBCT_TBL_FIREWALL_DATA );
        $fw_updater->update();

        return true;
	}
	static public function apbct_sfw_send_logs($access_key = '') {
	    if( empty( $access_key ) ){
        	$access_key = \IPS\Settings::i()->ct_access_key;
        	if (empty($access_key)) {
        		return false;
        	}
	    }

        $firewall = new Firewall( $access_key, DB::getInstance(), APBCT_TBL_FIREWALL_LOG );
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

    public static function spamCheck($reg_flag = false)
    {
        $ct_access_key = \IPS\Settings::i()->ct_access_key;

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
        $arr = array(
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
        );
        $sender_info = json_encode($arr);
        $arr = array(
            'comment_type' => 'register',
        );

        $post_info = json_encode($arr);

        if ( $sender_info === false ) {
            $sender_info = '';
        }
        if ( $post_info === false ) {
            $post_info = '';
        }
        $config_key = $ct_access_key;
        $ct = new Cleantalk();
        $ct->server_url = \IPS\Settings::i()->ct_server_url;
        $ct->work_url = \IPS\Settings::i()->ct_work_url;
        $ct->server_ttl = \IPS\Settings::i()->ct_server_ttl;
        $ct->server_changed = \IPS\Settings::i()->ct_server_changed;

        $sender_email = filter_var($_POST['email_address'], FILTER_SANITIZE_EMAIL);

        $ct_request = new CleantalkRequest();
        $ct_request->auth_key = $config_key;
        $ct_request->sender_nickname = $_POST['username'];

        $ct_request->sender_ip = Helper::ipGet('real', false);
        $ct_request->x_forwarded_for = Helper::ipGet('x_forwarded_for', false);
        $ct_request->x_real_ip = Helper::ipGet('x_real_ip', false);

        $ct_request->sender_email = $sender_email;
        $ct_request->sender_info = $sender_info;
        $ct_request->post_info = $post_info;
        $ct_request->agent = 'ips5-' . self::getAntispamModuleVersion();
        $ct_request->js_on = isset($_COOKIE['ct_checkjs']) && in_array($_COOKIE['ct_checkjs'], self::getCheckJSArray()) ? 1 : 0;
        $ct_request->submit_time = isset($_COOKIE['ct_ps_timestamp']) ? time() - (int)$_COOKIE['ct_ps_timestamp'] : 0;
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
