<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Session;

use A1comms\GaeSupportLaravel\Integration\Datastore\DatastoreFactory;
use Carbon\Carbon;
use GDS;
use Google\Cloud\Core\ExponentialBackoff;
use Illuminate\Support\Facades\Log;
use SessionHandlerInterface;

/**
 * class DataStoreSessionHandler.
 *
 * @uses SessionHandlerInterface
 */
class DatastoreSessionHandler implements SessionHandlerInterface
{
    /**
     * $expire.
     *
     * @var mixed
     */
    private $expire;

    /**
     * $lastaccess.
     *
     * @var mixed
     */
    private $lastaccess;

    /**
     * $deleteTime.
     *
     * @var mixed
     */
    private $deleteTime;

    /**
     * $obj_schema.
     *
     * @var mixed
     */
    private $obj_schema;

    /**
     * $obj_store.
     *
     * @var mixed
     */
    private $obj_store;

    /**
     * $orig_data.
     *
     * @var mixed
     */
    private $orig_data;

    /**
     * $orig_id.
     *
     * @var mixed
     */
    private $orig_id;

    /**
     * __construct.
     *
     * @return mixed value
     */
    public function __construct()
    {
        // Get session max lifetime to leverage Memcache expire functionality.
        $this->expire     = ini_get('session.gc_maxlifetime');
        $this->lastaccess = $this->getTimeStamp();
        $this->deleteTime = Carbon::now()->subSeconds($this->expire)->toDateTimeString();

        $obj_gateway = DatastoreFactory::make();

        $this->obj_schema = (new GDS\Schema('sessions'))
            ->addString('data', false)
            ->addDateTime('lastaccess')
        ;

        $this->obj_store = new GDS\Store($this->obj_schema, $obj_gateway);
    }

    /**
     * open - Re-initializes existing session, or creates a new one.
     *
     * @param string $savePath    Save path
     * @param string $sessionName Session name
     *
     * @return bool
     */
    public function open($savePath, $sessionName)
    {
        return true;
    }

    /**
     * close - Closes the current session.
     *
     * @return bool
     */
    public function close()
    {
        return true;
    }

    /**
     * read - Reads the session data.
     *
     * @param string $id session ID
     *
     * @return string
     */
    public function read($id)
    {
        $obj_sess = (new ExponentialBackoff(6, [DatastoreFactory::class, 'shouldRetry']))->execute([$this->obj_store, 'fetchByName'], [$id]);

        if ($obj_sess instanceof GDS\Entity) {
            $this->orig_id   = $id;
            $this->orig_data = $obj_sess->data;

            return $obj_sess->data;
        }

        return '';
    }

    /**
     * write - Writes the session data to the storage.
     *
     * @param string $id   Session ID
     * @param string $data Serialized session data to save
     *
     * @return string
     */
    public function write($id, $data)
    {
        $obj_sess = $this->obj_store->createEntity([
            'data'       => $data,
            'lastaccess' => $this->lastaccess,
        ])->setKeyName($id);

        if (($this->orig_id !== $id) || ($this->orig_data !== $data)) {
            /**
             * If Datastore returns too much contention on write,
             * keep retrying with exponential backoff, 6 times until we fail.
             */
            $result = (new ExponentialBackoff(6, [DatastoreFactory::class, 'shouldRetry']))->execute([$this->obj_store, 'upsert'], [$obj_sess]);
        }

        return true;
    }

    /**
     * destroy - Destroys a session.
     *
     * @param tring $id Session ID
     *
     * @return bool
     */
    public function destroy($id)
    {
        $obj_sess = (new ExponentialBackoff(6, [DatastoreFactory::class, 'shouldRetry']))->execute([$this->obj_store, 'fetchByName'], [$id]);

        if ($obj_sess instanceof GDS\Entity) {
            $result = (new ExponentialBackoff(6, [DatastoreFactory::class, 'shouldRetry']))->execute([$this->obj_store, 'delete'], [$obj_sess]);
        }

        return true;
    }

    /**
     * gc - Cleans up expired sessions (garbage collection).
     *
     * @param int|string $maxlifetime Sessions that have not updated for the last maxlifetime seconds will be removed
     *
     * @return bool
     */
    public function gc($maxlifetime)
    {
        return true;
    }

    /**
     * googlegc - Cleans up expired sessions in GAE datastore (garbage collection).
     *
     * @return mixed value
     */
    public function googlegc()
    {
        $this->obj_store->query('SELECT * FROM sessions WHERE lastaccess < @old', ['old' => $this->deleteTime]);

        while ($arr_page = $this->obj_store->fetchPage(100)) {
            Log::info('Processing page of '.\count($arr_page).' records...');

            if (!empty($arr)) {
                $this->obj_store->delete($arr_page);
            }
        }
    }

    private function getTimeStamp()
    {
        return Carbon::now()->toDateTimeString();
    }
}
