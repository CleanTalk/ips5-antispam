<?php
/**
 * @brief        Member Listener
 * @author        <a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright    (c) Invision Power Services, Inc.
 * @license        https://www.invisioncommunity.com/legal/standards/
 * @package        Invision Community
 * {subpackage}
 * @since        12 Mar 2025
 */

namespace IPS\antispambycleantalk\listeners;

/* To prevent PHP errors (extending class does not exist) revealing path */

use IPS\antispambycleantalk\Application;
use IPS\Events\ListenerType\MemberListenerType;
use IPS\Member as MemberClass;

use function defined;

if ( !defined('\IPS\SUITE_UNIQUE_KEY') ) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * Member Listener
 */
class Register extends MemberListenerType
{
    public function onCreateAccount(MemberClass $member)
    {
        $ct_result = Application::spamCheck(true);

        if ( $ct_result && isset($ct_result->errno) && $ct_result->errno == 0 && $ct_result->allow == 0 ) {
            // Spammer - delete this user
            $member->delete();
        }
    }
}
