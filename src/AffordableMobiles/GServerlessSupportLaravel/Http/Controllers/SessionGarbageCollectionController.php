<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Http\Controllers;

use AffordableMobiles\GServerlessSupportLaravel\Session\DatastoreSessionHandler;
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
