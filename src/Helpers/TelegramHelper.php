<?php

namespace Helpers;

class TelegramHelper {
    /**
     * Escapes text for Telegram MarkdownV2 parse mode.
     *
     * Characters to escape: '_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'
     * Note: '.' and '!' only need escaping if they are part of a sequence that could be misinterpreted.
     * For simplicity and safety, we escape them generally.
     *
     * @param string $text The text to escape.
     * @return string The escaped text.
     */
    public static function escapeMarkdownV2(string $text): string {
        $escapeChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        $escapedText = $text;
        foreach ($escapeChars as $char) {
            $escapedText = str_replace($char, '\\' . $char, $escapedText);
        }
        return $escapedText;
    }
}
?>
