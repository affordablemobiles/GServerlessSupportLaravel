<?php

use \A1comms\GaeSupportLaravel\Http\Controllers\SessionGarbageCollectionController;

/**
 * Maintenance routes.
 */
Route::get('gae/sessiongc',  array('as' => 'sessiongc',
    'uses' => SessionGarbageCollectionController::class.'@run'));
