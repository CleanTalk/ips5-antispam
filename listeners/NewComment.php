<?php

namespace IPS\antispambycleantalk\listeners;

use IPS\Content as ContentClass;
use IPS\Content\Comment as CommentClass;

if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) ) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

class NewComment extends NewPost
{
    public static string $class = CommentClass::class;

    protected static function getMessage(ContentClass $object, array $values): string
    {
        $itemClass = $object::$itemClass;
        $content = $values[$itemClass::$formLangPrefix . 'comment'] ?? $object->mapped('content');

        return is_string($content) ? trim(strip_tags($content)) : '';
    }
}
