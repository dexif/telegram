<?php

namespace FlarumTelegram\Controllers;

use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\User\LoginProvider;
use FlarumTelegram\Auth\TelegramAuthValidator;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * Links a Telegram account to the current user. Called from the settings page
 * with the data passed to the Telegram Login Widget's data-onauth callback.
 */
class LinkTelegramController implements RequestHandlerInterface
{
    public function __construct(
        protected TelegramAuthValidator $validator
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        try {
            $auth = $this->validator->validate((array) $request->getParsedBody());
        } catch (RuntimeException $e) {
            throw new ValidationException(['telegram' => $e->getMessage()]);
        }

        $existing = LoginProvider::where('provider', 'telegram')
            ->where('identifier', $auth['id'])
            ->first();

        if ($existing && (int) $existing->user_id !== $actor->id) {
            throw new ValidationException(['telegram' => 'This Telegram account is already linked to another user.']);
        }

        if (! $existing) {
            $actor->loginProviders()->where('provider', 'telegram')->delete();
            $actor->loginProviders()->create(['provider' => 'telegram', 'identifier' => $auth['id']]);
        }

        $actor->flagrow_telegram_error = null;
        $actor->save();

        return new EmptyResponse(204);
    }
}
