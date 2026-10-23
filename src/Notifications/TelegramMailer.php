<?php

namespace FlarumTelegram\Notifications;

use Flarum\Http\UrlGenerator;
use Flarum\Locale\TranslatorInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Throwable;

class TelegramMailer
{
    /**
     * Telegram's limit for a single message.
     */
    protected const MAX_LENGTH = 4096;

    public function __construct(
        protected TranslatorInterface $translator,
        protected SettingsRepositoryInterface $settings,
        protected UrlGenerator $url,
        protected Factory $view,
        protected LoggerInterface $logger
    ) {
    }

    public function send(MailableInterface&BlueprintInterface $blueprint, User $user): void
    {
        $token = $this->settings->get('dexif-telegram.botToken');
        $chatId = $user->loginProviders()->where('provider', 'telegram')->value('identifier');

        if (! $token || ! $chatId) {
            return;
        }

        try {
            (new Client(['timeout' => 10]))->post("https://api.telegram.org/bot$token/sendMessage", [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => Str::limit($this->render($blueprint, $user), self::MAX_LENGTH - 3),
                    'link_preview_options' => ['is_disabled' => true],
                ],
            ]);
        } catch (ClientException $exception) {
            $status = $exception->getResponse()->getStatusCode();

            // 400 (chat not found) and 403 (blocked / never started) are on the user's side.
            if ($status === 400 || $status === 403) {
                $description = Arr::get(json_decode((string) $exception->getResponse()->getBody(), true) ?: [], 'description', '');

                $user->flagrow_telegram_error = str_contains($description, 'blocked by the user') ? 'blocked' : 'unauthorized';
                $user->save();
            } else {
                $this->logger->warning("Telegram notification failed: Telegram API responded with status $status");
            }

            return;
        } catch (Throwable $exception) {
            // Never let Telegram break the request that triggered the notification (the default
            // queue is synchronous). Guzzle messages contain the request URL with the bot token,
            // so only the class is logged for them.
            $message = $exception instanceof GuzzleException ? get_class($exception) : $exception->getMessage();
            $this->logger->warning("Telegram notification failed: $message");

            return;
        }

        if ($user->flagrow_telegram_error) {
            $user->flagrow_telegram_error = null;
            $user->save();
        }
    }

    /**
     * Renders the notification's plain-text email view, with the same view data
     * as Flarum\Notification\NotificationMailer.
     */
    protected function render(MailableInterface&BlueprintInterface $blueprint, User $user): string
    {
        $this->translator->setLocale($user->getPreference('locale') ?? $this->settings->get('default_locale'));

        $settingsLink = $this->url->to('forum')->route('settings');

        $data = [
            'blueprint' => $blueprint,
            'user' => $user,
            // There is no per-type unsubscribe for Telegram, point to the notification settings instead.
            'unsubscribeLink' => $settingsLink,
            'settingsLink' => $settingsLink,
            'type' => $blueprint::getType(),
            'forumTitle' => $this->settings->get('forum_title'),
            'username' => $user->display_name,
            'userEmail' => $user->email,
            'title' => $blueprint->getEmailSubject($this->translator),
        ];

        // Mail components read these from shared data, not from the parent view.
        $this->view->share($data);

        $text = $this->view->make($blueprint->getEmailViews()['text'], $data)->render();

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
