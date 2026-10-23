<?php

namespace FlarumTelegram\Controllers;

use Flarum\Forum\Auth\Registration;
use Flarum\Forum\Auth\ResponseFactory;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use FlarumTelegram\Auth\TelegramAuthValidator;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * Callback for the Telegram Login Widget (data-auth-url). Telegram redirects
 * the browser here with the signed user data in the query string.
 *
 * Linking Telegram to an already logged in account is handled by
 * LinkTelegramController through the API, which is CSRF-protected.
 */
class TelegramAuthController implements RequestHandlerInterface
{
    public function __construct(
        protected ResponseFactory $response,
        protected TelegramAuthValidator $validator,
        protected UrlGenerator $url
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $returnTo = $this->url->to('forum')->base().'/';

        if (! RequestUtil::getActor($request)->isGuest()) {
            return new RedirectResponse($returnTo);
        }

        try {
            $auth = $this->validator->validate($request->getQueryParams());
        } catch (RuntimeException $e) {
            throw new ValidationException(['telegram' => $e->getMessage()]);
        }

        return $this->response->make(
            'telegram',
            $auth['id'],
            function (Registration $registration) use ($auth) {
                if (! empty($auth['username'])) {
                    $registration->suggestUsername($auth['username']);
                }

                if (! empty($auth['photo_url'])) {
                    $registration->provideAvatar($auth['photo_url']);
                }

                $registration->setPayload($auth);
            },
            $returnTo
        );
    }
}
