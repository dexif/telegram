import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import LogInButtons from 'flarum/forum/components/LogInButtons';
import Alert from 'flarum/common/components/Alert';
import TelegramLoginWidget from './components/TelegramLoginWidget';

export { default as extend } from './extend';

app.initializers.add('dexif-telegram', () => {
  const botUsername = () => app.forum.attribute('dexif-telegram.botUsername');
  const notificationsEnabled = () => app.forum.attribute('dexif-telegram.enableNotifications');

  extend(LogInButtons.prototype, 'items', function (items) {
    if (botUsername()) {
      items.add('dexif-telegram', <TelegramLoginWidget />);
    }
  });

  // SettingsPage and NotificationGrid are lazy loaded, so they are extended by module path.
  extend('flarum/forum/components/NotificationGrid', 'notificationMethods', function (items) {
    const user = app.session.user;

    if (!notificationsEnabled() || !user || !user.canReceiveTelegramNotifications()) return;

    items.add('telegram', {
      name: 'telegram',
      icon: 'fab fa-telegram-plane',
      label: app.translator.trans('dexif-telegram.forum.settings.notify_by_telegram_heading'),
    });
  });

  extend('flarum/forum/components/SettingsPage', 'accountItems', function (items) {
    const user = app.session.user;

    if (!botUsername() || !user || user.canReceiveTelegramNotifications()) return;

    const link = (data) =>
      app
        .request({
          method: 'POST',
          url: app.forum.attribute('apiUrl') + '/telegram/link',
          body: data,
        })
        .then(() => {
          user.pushAttributes({ canReceiveTelegramNotifications: true, telegramError: null });
          app.alerts.show({ type: 'success' }, app.translator.trans('dexif-telegram.forum.settings.linked_message'));
          m.redraw();
        });

    items.add('dexif-telegram', <TelegramLoginWidget onauth={link} />);
  });

  extend('flarum/forum/components/SettingsPage', 'notificationsItems', function (items) {
    const user = app.session.user;

    if (!notificationsEnabled() || !user || !user.telegramError()) return;

    items.add(
      'dexif-telegram-error',
      <Alert dismissible={false}>
        {app.translator.trans('dexif-telegram.forum.settings.unblock_telegram_bot', {
          a: <a href={'https://t.me/' + botUsername()} target="_blank" rel="noopener noreferrer" />,
          username: '@' + botUsername(),
        })}
      </Alert>
    );
  });
});
