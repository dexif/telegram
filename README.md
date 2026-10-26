# Telegram login and notifications for Flarum

Based on the original extension by [Flagrow](https://discuss.flarum.org/d/1832-flagrow-extension-developer-group), with improvements from [Nodeloc](https://github.com/nodeloc/telegram).

[![MIT license](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/dexif/telegram/blob/master/LICENSE.md) [![Latest Stable Version](https://img.shields.io/packagist/v/dexif/telegram.svg)](https://packagist.org/packages/dexif/telegram) [![Total Downloads](https://img.shields.io/packagist/dt/dexif/telegram.svg)](https://packagist.org/packages/dexif/telegram)

This extension adds a "Log in with Telegram" button that uses the new [Telegram Login widget](https://telegram.org/blog/login) and add an option to receive notifications via Telegram as well.

<img width="279" alt="22" src="https://github.com/nodeloc/telegram/assets/149086144/a77b61c0-4dba-40f0-a978-c4b578340b95">

<img width="396" alt="33" src="https://github.com/nodeloc/telegram/assets/149086144/dc04c577-0325-4e8e-9f0a-00981ef7e92e">

<img width="473" alt="11" src="https://github.com/nodeloc/telegram/assets/149086144/10ea6339-93f8-4adf-8264-0a4fd2310319">


## Requirements

- Flarum 2.0+
- PHP 8.3+

## Installation

```bash
composer require dexif/telegram
```

## Updating

If you are migrating from `nodeloc/telegram`, replace the package (the extension ID changes to `dexif-telegram`, so re-enter the bot settings and enable the extension again):

```bash
composer remove nodeloc/telegram
composer require dexif/telegram
```

Otherwise:

```bash
composer update dexif/telegram
php flarum migrate
php flarum cache:clear
```

## Configuration

Follow [Telegram instructions](https://core.telegram.org/widgets/login#setting-up-a-bot) to create a bot for the login widget.

Then copy the **Bot Username** and **Bot Token** to the extension settings. The username and token must belong to the same bot.

Don't forget to bind your forum domain to the bot with the `/setdomain` command in [@BotFather](https://t.me/BotFather), otherwise the widget will show "Bot domain invalid".

Existing users can link their Telegram account on the **Security** page of their profile.

If you check **Enable Notifications**, the login widget will ask for permission for the bot to message the user and an additional Telegram column will appear in the user's notification settings. Notifications are sent through the queue and use the plain-text version of the notification e-mail.

## Support our work

We prefer to keep our work available to everyone.

## Security

If you discover a security vulnerability within Telegram login and notifications, please send an email to security@spitsyn.net. All security vulnerabilities will be promptly addressed.

Please include as many details as possible. You can use `php flarum info` to get the PHP, Flarum and extension versions installed.

## Links

- [Source code on GitHub](https://github.com/dexif/telegram)
- [Report an issue](https://github.com/dexif/telegram/issues)
- [Download via Packagist](https://packagist.org/packages/dexif/telegram)

