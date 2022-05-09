<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Http\Controllers;

use A1comms\GaeSupportLaravel\Session\DatastoreSessionHandler;
use Illuminate\Routing\Controller;

/**
 * SessionGarbageCollectionController.
 *
 * @uses     Controller
 *
 * @category  GaeSupportL5
 */
class SessionGarbageCollectionController extends Controller
{
    /**
     * run.
     */
    public function run(): void
    {
        $s = new DatastoreSessionHandler();
        $s->googlegc();
    }
}
