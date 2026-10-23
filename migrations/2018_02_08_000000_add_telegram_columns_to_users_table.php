<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        // The columns may already exist when migrating from another fork of this
        // extension (e.g. nodeloc/telegram), whose migrations ran under a different extension ID.
        $hasId = $schema->hasColumn('users', 'flagrow_telegram_id');
        $hasError = $schema->hasColumn('users', 'flagrow_telegram_error');

        $schema->table('users', function (Blueprint $table) use ($hasId, $hasError) {
            if (! $hasId) {
                $table->bigInteger('flagrow_telegram_id')->nullable()->unique();
            }

            if (! $hasError) {
                $table->string('flagrow_telegram_error', 50)->nullable();
            }
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('users', function (Blueprint $table) {
            $table->dropColumn('flagrow_telegram_id');
            $table->dropColumn('flagrow_telegram_error');
        });
    },
];
