<?php

namespace FlarumTelegram\Job;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\Queue\AbstractJob;
use Flarum\User\User;
use FlarumTelegram\Notifications\TelegramMailer;

class SendTelegramNotificationJob extends AbstractJob
{
    public function __construct(
        private readonly MailableInterface&BlueprintInterface $blueprint,
        private readonly User $recipient
    ) {
        parent::__construct();
    }

    public function handle(TelegramMailer $mailer): void
    {
        $mailer->send($this->blueprint, $this->recipient);
    }
}
