<?php

namespace FlarumTelegram\Driver;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\Driver\NotificationDriverInterface;
use Flarum\Notification\MailableInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use FlarumTelegram\Job\SendTelegramNotificationJob;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\Queue;
use ReflectionClass;

class TelegramNotificationDriver implements NotificationDriverInterface
{
    public function __construct(
        protected Container $container,
        protected SettingsRepositoryInterface $settings
    ) {
    }

    public function send(BlueprintInterface $blueprint, array $users): void
    {
        // Telegram messages are rendered from the notification's plain-text email view.
        if (! $blueprint instanceof MailableInterface) {
            return;
        }

        if (! $this->settings->get('dexif-telegram.enableNotifications') || ! $this->settings->get('dexif-telegram.botToken')) {
            return;
        }

        // Resolved at push time, see Flarum\Notification\Driver\EmailNotificationDriver.
        $queue = $this->container->make(Queue::class);

        foreach ($users as $user) {
            if ($user->getPreference(User::getNotificationPreferenceKey($blueprint::getType(), 'telegram'))) {
                $queue->push(new SendTelegramNotificationJob($blueprint, $user));
            }
        }
    }

    public function registerType(string $blueprintClass, array $driversEnabledByDefault): void
    {
        if ((new ReflectionClass($blueprintClass))->implementsInterface(MailableInterface::class)) {
            User::registerPreference(
                User::getNotificationPreferenceKey($blueprintClass::getType(), 'telegram'),
                boolval(...),
                in_array('telegram', $driversEnabledByDefault)
            );
        }
    }
}
