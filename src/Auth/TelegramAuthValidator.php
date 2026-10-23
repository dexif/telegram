<?php

namespace FlarumTelegram\Auth;

use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * Validates data returned by the Telegram Login Widget.
 *
 * @see https://core.telegram.org/widgets/login#checking-authorization
 */
class TelegramAuthValidator
{
    /**
     * Fields Telegram signs. Anything else in the request is ignored so that
     * extra query parameters can't break or influence the hash check.
     */
    protected const FIELDS = ['id', 'first_name', 'last_name', 'username', 'photo_url', 'auth_date'];

    protected const MAX_AGE = 86400;

    public function __construct(
        protected SettingsRepositoryInterface $settings
    ) {
    }

    /**
     * @return array<string, string> the verified auth data
     * @throws RuntimeException
     */
    public function validate(array $input): array
    {
        $token = $this->settings->get('dexif-telegram.botToken');

        if (! $token) {
            throw new RuntimeException('No bot token configured for Telegram');
        }

        $checkHash = Arr::get($input, 'hash');
        $data = array_filter(
            Arr::only($input, self::FIELDS),
            fn ($value) => is_scalar($value) && $value !== ''
        );

        if (! is_string($checkHash) || empty($data['id']) || empty($data['auth_date'])) {
            throw new RuntimeException('Incomplete Telegram auth data');
        }

        ksort($data);

        $dataCheckString = implode("\n", array_map(
            fn ($key, $value) => "$key=$value",
            array_keys($data),
            $data
        ));

        $secretKey = hash('sha256', $token, true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($hash, $checkHash)) {
            throw new RuntimeException('Data is not from Telegram');
        }

        if (time() - (int) $data['auth_date'] > self::MAX_AGE) {
            throw new RuntimeException('Telegram auth data is outdated');
        }

        return array_map('strval', $data);
    }
}
