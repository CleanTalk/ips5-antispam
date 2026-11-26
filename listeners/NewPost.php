<?php
/**
 * @brief		Content Listener
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
{subpackage}
 * @since		14 Apr 2025
 */

namespace IPS\antispambycleantalk\listeners;

/* To prevent PHP errors (extending class does not exist) revealing path */

use IPS\antispambycleantalk\Application;
use IPS\Content as ContentClass;
use IPS\Events\ListenerType\ContentListenerType;
use IPS\Member;
use IPS\Settings;
use function defined;

if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
	exit;
}

/**
 * Content Listener
 */
class NewPost extends ContentListenerType
{
 	/**
 	 * @brief	[Required] The class that is handled by this listener
 	 * @var string
 	 */
 	public static string $class = \IPS\Content\Item::class;

    public function onBeforeCreateOrEdit(ContentClass $object, array $values, bool $new = false): void
    {
        $member = Member::loggedIn();
        if ( !$new || Settings::i()->ct_moderate_new != 1 || !Application::canModerateContent($member) ) {
            return;
        }

        $guestName = $values['guest_name'] ?? null;
        Application::moderateContent(static::getMessage($object, $values), $member, is_string($guestName) ? $guestName : null);
    }

    /**
     * Content classes name their form fields after their own prefix, so the keys cannot be hardcoded.
     */
    protected static function getMessage(ContentClass $object, array $values): string
    {
        $prefix = $object::$formLangPrefix;

        $title = $values[$prefix . 'title'] ?? '';
        $content = $values[$prefix . 'content'] ?? '';

        if ( ! is_string($title) ) {
            $title = '';
        }

        if ( ! is_string($content) ) {
            $content = '';
        }

        return $title . ' ' . trim(strip_tags($content));
    }
}
