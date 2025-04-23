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
            $member &&
            \IPS\Settings::i()->ct_moderate_new == 1 &&
            $member->member_posts <= \IPS\Settings::i()->ct_posts_to_check &&
            $new &&
            ! $member->isAdmin()
        ) {
            $request_params = [
                'post_info' => [
                    'comment_type' => 'comment'
                ],
                'sender_email' => $member ? $member->email : '',
                'message' => $values['topic_title'] . ' ' . $values['topic_content'],
            ];
            if(isset($_POST['guest_name'])) {
                $request_params['sender_nickname'] = $_POST['guest_name'];
            } else {
                $request_params['sender_nickname'] = $member->name;
            }

            $ct_result = Application::spamCheck($request_params);

            if ( $ct_result && isset($ct_result->errno) && $ct_result->errno == 0 && $ct_result->allow == 0 ) {
                die($ct_result->comment);
            }
        }
    }
}
