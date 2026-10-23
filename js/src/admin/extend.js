import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';

export default [
  new Extend.Admin()
    .setting(() => ({
      setting: 'dexif-telegram.botUsername',
      type: 'text',
      label: app.translator.trans('dexif-telegram.admin.settings.field.botUsername'),
    }))
    .setting(() => ({
      setting: 'dexif-telegram.botToken',
      type: 'password',
      label: app.translator.trans('dexif-telegram.admin.settings.field.botToken'),
    }))
    .setting(() => ({
      setting: 'dexif-telegram.enableNotifications',
      type: 'boolean',
      label: app.translator.trans('dexif-telegram.admin.settings.field.enableNotifications'),
    })),
];
