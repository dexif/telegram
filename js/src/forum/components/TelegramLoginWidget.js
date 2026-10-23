import Component from 'flarum/common/Component';
import app from 'flarum/forum/app';

/**
 * Renders the Telegram Login Widget. The widget is a script that inserts an
 * iframe next to itself, so it is injected once on create and left alone by
 * Mithril afterwards.
 *
 * ### Attrs
 *
 * - `onauth` callback receiving the signed user data. Without it the widget
 *   redirects the browser to /auth/telegram (log in / sign up).
 */
export default class TelegramLoginWidget extends Component {
  view() {
    return <div className="TelegramLoginWidget" />;
  }

  oncreate(vnode) {
    super.oncreate(vnode);

    const script = document.createElement('script');

    script.async = true;
    script.src = 'https://telegram.org/js/telegram-widget.js?22';
    script.setAttribute('data-telegram-login', app.forum.attribute('dexif-telegram.botUsername'));
    script.setAttribute('data-size', 'large');
    script.setAttribute('data-radius', '10');

    if (app.forum.attribute('dexif-telegram.enableNotifications')) {
      script.setAttribute('data-request-access', 'write');
    }

    if (this.attrs.onauth) {
      window.flarumTelegramOnAuth = this.attrs.onauth;
      script.setAttribute('data-onauth', 'flarumTelegramOnAuth(user)');
    } else {
      script.setAttribute('data-auth-url', app.forum.attribute('baseUrl') + '/auth/telegram');
    }

    this.element.appendChild(script);
  }
}
