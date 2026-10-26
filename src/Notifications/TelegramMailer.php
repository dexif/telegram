<?php

namespace FlarumTelegram\Notifications;

use Flarum\Discussion\Discussion;
use Flarum\Http\UrlGenerator;
use Flarum\Locale\TranslatorInterface;
use Flarum\Mail\SafeSubstitution;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\Post\Post;
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

        $this->translator->setLocale($user->getPreference('locale') ?? $this->settings->get('default_locale'));

        $client = new Client(['timeout' => 10]);
        $url = "https://api.telegram.org/bot$token/sendMessage";

        try {
            $html = $this->renderHtml($blueprint);
            $sent = false;

            if ($html !== null) {
                try {
                    $this->sendMessage($client, $url, $chatId, ['text' => $html, 'parse_mode' => 'HTML']);
                    $sent = true;
                } catch (ClientException $exception) {
                    // Markup Telegram rejects is sent again as plain text.
                    $description = $this->errorDescription($exception);

                    if (! str_contains($description, "can't parse entities") && ! str_contains($description, 'message is too long')) {
                        throw $exception;
                    }

                    $this->logger->warning("Telegram notification sent as plain text: $description");
                }
            }

            if (! $sent) {
                $this->sendMessage($client, $url, $chatId, ['text' => Str::limit($this->renderText($blueprint, $user), self::MAX_LENGTH - 3)]);
            }
        } catch (ClientException $exception) {
            $status = $exception->getResponse()->getStatusCode();

            // 400 (chat not found) and 403 (blocked / never started) are on the user's side.
            if ($status === 400 || $status === 403) {
                $description = $this->errorDescription($exception);

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

    protected function sendMessage(Client $client, string $url, string $chatId, array $message): void
    {
        $client->post($url, [
            'json' => $message + [
                'chat_id' => $chatId,
                'link_preview_options' => ['is_disabled' => true],
            ],
        ]);
    }

    protected function errorDescription(ClientException $exception): string
    {
        return (string) Arr::get(json_decode((string) $exception->getResponse()->getBody(), true) ?: [], 'description', '');
    }

    /**
     * Renders notifications about a post as Telegram HTML: the subject linking to the
     * post, followed by the formatted post content. Returns null for other notifications
     * or if rendering fails, the plain-text email view is used then.
     */
    protected function renderHtml(MailableInterface&BlueprintInterface $blueprint): ?string
    {
        $post = $this->notificationPost($blueprint);

        if (! $post || ! method_exists($post, 'formatContent')) {
            return null;
        }

        try {
            $title = $blueprint->getEmailSubject($this->translator);
            $link = $this->postLink($post);

            $header = '<b><a href="'.htmlspecialchars($link, ENT_QUOTES).'">'.htmlspecialchars($title, ENT_NOQUOTES).'</a></b>';

            // Leave room for the title and the line breaks and bullets the converter adds.
            $body = (new TelegramHtml($this->url->to('forum')->base()))
                ->convert($post->formatContent(), self::MAX_LENGTH - mb_strlen($title) - 500);

            return $body === '' ? $header : "$header\n\n$body";
        } catch (Throwable $exception) {
            $this->logger->warning('Telegram notification HTML rendering failed: '.$exception->getMessage());

            return null;
        }
    }

    /**
     * The post a notification is about. Mentions keep the mentioning post in `reply`,
     * other notifications in `post` or as the subject.
     */
    protected function notificationPost(BlueprintInterface $blueprint): ?Post
    {
        foreach (['reply', 'post'] as $property) {
            if (isset($blueprint->$property) && $blueprint->$property instanceof Post) {
                return $blueprint->$property;
            }
        }

        $subject = $blueprint->getSubject();

        return $subject instanceof Post ? $subject : null;
    }

    protected function postLink(Post $post): string
    {
        return $this->url->to('forum')->route('discussion', ['id' => $post->discussion_id, 'near' => $post->number]);
    }

    /**
     * Renders the notification's plain-text email view, with the same view data
     * as Flarum\Notification\NotificationMailer.
     */
    protected function renderText(MailableInterface&BlueprintInterface $blueprint, User $user): string
    {
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

        // Mail views get a translator that replaces parameter values with markers, which
        // Flarum only swaps back when the email is sent (see Flarum\Mail\MutateEmail).
        if (class_exists(SafeSubstitution::class)) {
            $text = SafeSubstitution::restore($text, escape: false);
        }

        $text = trim(preg_replace("/\n{3,}/", "\n\n", $text));

        // Not every notification view links to the discussion, so make sure there is a link.
        $link = $this->subjectLink($blueprint);

        if ($link && ! str_contains($text, $this->url->to('forum')->path('d/'))) {
            $text .= "\n\n$link";
        }

        return $text;
    }

    protected function subjectLink(BlueprintInterface $blueprint): ?string
    {
        $subject = $blueprint->getSubject();

        if ($subject instanceof Post) {
            return $this->postLink($subject);
        }

        if ($subject instanceof Discussion) {
            return $this->url->to('forum')->route('discussion', ['id' => $subject->id]);
        }

        return null;
    }
}
