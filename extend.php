<?php

namespace FlarumTelegram;

use Flarum\Api\Context;
use Flarum\Api\Resource\UserResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\User\User;
use FlarumTelegram\Controllers\LinkTelegramController;
use FlarumTelegram\Controllers\TelegramAuthController;
use FlarumTelegram\Driver\TelegramNotificationDriver;

$isSelf = fn (User $user, Context $context) => $context->getActor()->id === $user->id;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/resources/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/resources/locale'),

    (new Extend\Routes('forum'))
        ->get('/auth/telegram', 'auth.telegram', TelegramAuthController::class),

    (new Extend\Routes('api'))
        ->post('/telegram/link', 'telegram.link', LinkTelegramController::class),

    (new Extend\Notification())
        ->driver('telegram', TelegramNotificationDriver::class),

    (new Extend\ApiResource(UserResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('canReceiveTelegramNotifications')
                ->visible($isSelf)
                ->get(fn (User $user) => $user->loginProviders()->where('provider', 'telegram')->exists()),
            Schema\Str::make('telegramError')
                ->visible($isSelf)
                ->nullable()
                ->get(fn (User $user) => $user->flagrow_telegram_error),
        ]),

    (new Extend\Settings())
        ->serializeToForum('dexif-telegram.botUsername', 'dexif-telegram.botUsername')
        ->serializeToForum('dexif-telegram.enableNotifications', 'dexif-telegram.enableNotifications', 'boolval'),
];
