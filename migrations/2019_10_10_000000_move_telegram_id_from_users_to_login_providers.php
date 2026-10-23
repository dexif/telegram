<?php

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();

        $users = $db->table('users')->whereNotNull('flagrow_telegram_id')->get(['id', 'flagrow_telegram_id']);

        foreach ($users as $user) {
            $exists = $db->table('login_providers')
                ->where('provider', 'telegram')
                ->where('identifier', (string) $user->flagrow_telegram_id)
                ->exists();

            if (! $exists) {
                $db->table('login_providers')->insert([
                    'user_id' => $user->id,
                    'provider' => 'telegram',
                    'identifier' => (string) $user->flagrow_telegram_id,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    },
    'down' => function (Builder $schema) {
        // do nothing
    }
];
