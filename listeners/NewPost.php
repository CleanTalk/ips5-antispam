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
use IPS\Content\Comment as CommentClass;
use IPS\Content\Item as ItemClass;
use IPS\Db;
use IPS\Events\ListenerType\ContentListenerType;
use IPS\Member;
use IPS\Node\Model;
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

    public function onBeforeCreateOrEdit(ContentClass $object, array $values, bool $new = false)
    {
        $member = Member::loggedIn();
        if (
            \IPS\Settings::i()->ct_moderate_new != 1 ||
            ! $new ||
            $member->member_posts > \IPS\Settings::i()->ct_posts_to_check ||
            $member->isAdmin()
        ) {
            return;
        }

        $message = trim(static::getMessage($object, $values));

        if ( $message === '' ) {
            return;
        }

        $request_params = [
            'post_info' => [
                'comment_type' => 'comment'
            ],
            'sender_email' => $member->email,
            'sender_nickname' => $values['guest_name'] ?? $member->name,
            'message' => $message,
        ];

        $ct_result = Application::spamCheck($request_params);

        if ( $ct_result && isset($ct_result->errno) && $ct_result->errno == 0 && $ct_result->allow == 0 ) {
            \IPS\Output::i()->error($ct_result->comment, 'antispambycleantalk_blocked', 403, '');
        }
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
