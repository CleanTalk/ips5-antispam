<?php

namespace IPS\antispambycleantalk\extensions\core\Loader;

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
        if ( Dispatcher::hasInstance() && Dispatcher::checkLocation('front') ) {
            Output::i()->jsFilesAsync[] = 'https://moderate.cleantalk.org/ct-bot-detector-wrapper.js';
        }
    }
}
