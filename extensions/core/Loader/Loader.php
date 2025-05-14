<?php

namespace IPS\antispambycleantalk\extensions\core\Loader;

use Cleantalk\Common\Cron\Cron;
use Cleantalk\Common\Firewall\Firewall;
use Cleantalk\Common\Firewall\Modules\Sfw;
use Cleantalk\Common\Variables\Server;
use Cleantalk\Custom\RemoteCalls;
use Cleantalk\Custom\StorageHandler\StorageHandler;
use IPS\Dispatcher;
use IPS\Extensions\LoaderAbstract;
use IPS\Output;

use function defined;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Loader extension: Loader
 * IMPORTANT: Most methods in this extension are NOT called
 * on Database Pages (@see \IPS\cms\Databases\Dispatcher)
 * The above dispatcher class bypasses CSS and JS intentionally.
 * Redirects can be handled in a Raw HTML block for now.
 */
class Loader extends LoaderAbstract
{
    public function onFinish() : void
    {
        if ( ! \IPS\Settings::i()->ct_access_key ) {
            return;
        }

        if ( Dispatcher::hasInstance() && Dispatcher::checkLocation('front') ) {
            Output::i()->jsFilesAsync[] = 'https://moderate.cleantalk.org/ct-bot-detector-wrapper.js';
        }

        if( \IPS\Settings::i()->ct_show_link == 1 ) {
            $html = Output::i()->output;
            Output::i()->output = $html . "<div id='cleantalk_footer_link' style='width:100%;text-align:center;'><a href='https://cleantalk.org/ips-cs-4-anti-spam-plugin'>IPS spam</a> blocked by CleanTalk.</div>";
        }

        $this->runCron();
        $this->runRc();

        if(
            \IPS\Settings::i()->ct_cleantalk_sfw == 1 &&
            !\IPS\Request::i()->isAjax() &&
            Dispatcher::checkLocation('front')
        )
        {
            $firewall = new Firewall(
                \IPS\Settings::i()->ct_access_key,
                APBCT_TBL_FIREWALL_LOG
            );

            $firewall->loadFwModule( new Sfw(
                APBCT_TBL_FIREWALL_LOG,
                APBCT_TBL_FIREWALL_DATA,
                array(
                    'sfw_counter'   => 0,
                    'cookie_domain' => Server::get('HTTP_HOST'),
                    'set_cookies'    => 1,
                )
            ) );

            //$firewall->run();

        }
        // Remote calls

    }
    private function runCron()
    {
        $cron = new Cron();
        $cron_name = $cron->getCronOptionName();
        if (!\IPS\Settings::i()->$cron_name) {
            $cron->addTask( 'sfw_update', 'apbct_sfw_update', 86400, time() + 60 );
            $cron->addTask( 'sfw_send_logs', 'apbct_sfw_send_logs', 3600 );
        }
        $tasks_to_run = $cron->checkTasks(); // Check for current tasks. Drop tasks inner counters.
        if(
            ! empty( $tasks_to_run ) && // There is tasks to run
            ! RemoteCalls::check() && // Do not doing CRON in remote call action
            (
                ! defined( 'DOING_CRON' ) ||
                ( defined( 'DOING_CRON' ) && DOING_CRON !== true )
            )
        ){
            $cron_res = $cron->runTasks( $tasks_to_run );
            // Handle the $cron_res for errors here.
        }
    }

    private function runRc()
    {
        if( RemoteCalls::check() ) {
            $storage_handler = new StorageHandler();
            $rc = new RemoteCalls(\IPS\Settings::i()->ct_access_key, $storage_handler);
            $rc->process();
        }
    }
}
