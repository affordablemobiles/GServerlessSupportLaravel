<?php

declare(strict_types=1);

use AffordableMobiles\GServerlessSupportLaravel\Http\Controllers\SessionGarbageCollectionController;

// Maintenance routes.
if (!is_lumen()) {
    Route::get('gae/sessiongc', ['as' => 'sessiongc',
        'uses'                        => SessionGarbageCollectionController::class.'@run', ]);
}
