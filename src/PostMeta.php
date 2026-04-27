<?php
declare(strict_types=1);

namespace FutureDrafts;

final class PostMeta
{
    public const KEY = '_future_draft_remind_on';

    public function register(): void
    {
        register_post_meta('post', self::KEY, [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => false,
            'auth_callback'     => static fn (): bool => current_user_can('edit_posts'),
            'sanitize_callback' => [self::class, 'sanitize'],
        ]);
    }

    /**
     * Accept only `YYYY-MM-DD`. Reject anything malformed by returning ''.
     */
    public static function sanitize(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }
        $parts = explode('-', $value);
        [$y, $m, $d] = [(int) $parts[0], (int) $parts[1], (int) $parts[2]];
        if (!checkdate($m, $d, $y)) {
            return '';
        }
        return $value;
    }
}
