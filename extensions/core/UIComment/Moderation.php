<?php

namespace IPS\antispambycleantalk\extensions\core\UIComment;

use IPS\antispambycleantalk\Application;
use IPS\Content\Comment;
use IPS\Content\Item;
use IPS\Helpers\Form;
use IPS\Helpers\Form\Editor;
use IPS\IPS;
use IPS\Login;
use IPS\Member;
use IPS\Output\UI\Comment as UIComment;
use IPS\Request;
use IPS\Session;
use IPS\Settings;

if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) ) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

class Moderation extends UIComment
{
    public function formElements(?Comment $comment, Item $item): array
    {
        $settings = Settings::i();
        if (
            $comment !== null ||
            ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
            !$settings->ct_access_key ||
            $settings->ct_check_comments != 1
        ) {
            return [];
        }

        $member = Member::loggedIn();
        $itemId = $item->{$item::$databaseColumnId};
        $submission = 'commentform_' . $itemId . '_submitted';
        $request = Request::i();
        if (
            !isset($request->$submission) ||
            !Login::compareHashes(Session::i()->csrfKey, (string) $request->csrfKey) ||
            !Application::canModerateContent($member)
        ) {
            return [];
        }

        // Match Item::commentFormElements() and add the check to the native editor validation.
        return [
            'editor' => new Editor($item::$formLangPrefix . 'comment_' . $itemId, null, true, [
                'app' => $item::$application,
                'key' => IPS::mb_ucfirst($item::$module),
                'autoSaveKey' => 'reply-' . $item::$application . '/' . $item::$module . '-' . $itemId,
                'contentClass' => $item::class,
                'contentId' => $itemId,
                'comments' => $item->commentsUseCommentEditor(),
            ], static function ($value) use ($item, $member, $request): void {
                if ( !$item->mergeConcurrentComment() ) {
                    Form::floodCheck();
                }

                $guestName = $request->guest_name;
                if ( is_string($value) ) {
                    Application::moderateContent(strip_tags($value), $member, is_string($guestName) ? $guestName : null);
                }
            }),
        ];
    }
}
