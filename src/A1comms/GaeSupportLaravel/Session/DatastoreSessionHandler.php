<?php

namespace A1comms\GaeSupportLaravel\Session;

use GDS;
use Carbon\Carbon;
use SessionHandlerInterface;
use Illuminate\Support\Facades\Log;
use Google\Cloud\Core\ExponentialBackoff;
use A1comms\GaeSupportLaravel\Integration\Datastore\DatastoreFactory;

/**
 * class DataStoreSessionHandler
 *
 * @uses SessionHandlerInterface
 *
 * @package A1comms\GaeSupportLaravel\Session
 */
class DatastoreSessionHandler implements SessionHandlerInterface
{
    /**
     * $expire
     *
     * @var mixed
     *
     * @access private
     */
    private $expire;

    /**
     * $lastaccess
     *
     * @var mixed
     *
     * @access private
     */
    private $lastaccess;

    /**
     * $deleteTime
     *
     * @var mixed
     *
     * @access private
     */
    private $deleteTime;

    /**
     * $obj_schema
     *
     * @var mixed
     *
     * @access private
     */
    private $obj_schema;

    /**
     * $obj_store
     *
     * @var mixed
     *
     * @access private
     */
    private $obj_store;

    /**
     * $orig_data
     *
     * @var mixed
     *
     * @access private
     */
    private $orig_data;

    /**
     * $orig_id
     *
     * @var mixed
     *
     * @access private
     */
    private $orig_id;

    /**
     * __construct
     *
     * @access public
     *
     * @return mixed Value.
     */
    public function __construct()
    {
        // Get session max lifetime to leverage Memcache expire functionality.
        $this->expire = ini_get("session.gc_maxlifetime");
        $this->lastaccess = $this->getTimeStamp();
        $this->deleteTime = Carbon::now()->subSeconds($this->expire)->toDateTimeString();

        $obj_gateway = DatastoreFactory::make();

        $this->obj_schema = (new GDS\Schema('sessions'))
            ->addString('data', false)
            ->addDateTime('lastaccess');

        $this->obj_store = new GDS\Store($this->obj_schema, $obj_gateway);
    }

    /**
     * open - Re-initializes existing session, or creates a new one.
     *
     * @param string $savePath    Save path
     * @param string $sessionName Session name
     *
     * @access public
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
     * @access public
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
     * @param string $id Session ID.
     *
     * @access public
     *
     * @return string
     */
    public function read($id)
    {
        $obj_sess = $this->obj_store->fetchByName($id);

        if ($obj_sess instanceof GDS\Entity) {
            $this->orig_id = $id;
            $this->orig_data = $obj_sess->data;

            return $obj_sess->data;
        }

        return "";
    }

    /**
     * write - Writes the session data to the storage
     *
     * @param string $id   Session ID
     * @param string $data Serialized session data to save
     *
     * @access public
     *
     * @return string
     */
    public function write($id, $data)
    {
        $obj_sess = $this->obj_store->createEntity([
            'data'          => $data,
            'lastaccess'    => $this->lastaccess
        ])->setKeyName($id);

        if (($this->orig_id != $id) || ($this->orig_data != $data)) {
            /**
             * If Datastore returns too much contention on write,
             * keep retrying with exponential backoff, 6 times until we fail.
             */
            $result = (new ExponentialBackoff(6, function ($ex, $retryAttempt = 1) {
                if (strpos((string)$ex, 'too much contention on these datastore entities') !== false) {
                    Log::info('ExponentialBackoff: retrying datastore upsert (session): too much contention on these datastore entities');
                    return true;
                } elseif (strpos((string)$ex, 'Connection reset by peer') !== false) {
                    Log::info('ExponentialBackoff: retrying datastore upsert (session): Connection reset by peer');
                    return true;
                }

                return false;
            }))->execute([$this->obj_store, 'upsert'], [$obj_sess]);
        }

        return true;
    }

    /**
     * destroy - Destroys a session.
     *
     * @param tring $id Session ID
     *
     * @access public
     *
     * @return bool
     */
    public function destroy($id)
    {
        $obj_sess = $this->obj_store->fetchByName($id);

        if ($obj_sess instanceof GDS\Entity) {
            $this->obj_store->delete($obj_sess);
        }

        return true;
    }

    /**
     * gc - Cleans up expired sessions (garbage collection).
     *
     * @param string|int $maxlifetime Sessions that have not updated for the last maxlifetime seconds will be removed
     *
     * @access public
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
     * @access public
     *
     * @return mixed Value.
     */
    public function googlegc()
    {
        $this->obj_store->query("SELECT * FROM sessions WHERE lastaccess < @old", ['old' => $this->deleteTime]);

        while ($arr_page = $this->obj_store->fetchPage(100)) {
            Log::info('Processing page of ' . count($arr_page) . ' records...');
            
            if (!empty($arr)) {
                $this->obj_store->delete($arr_page);
            }
        }
    }

    private function getTimeStamp()
    {
        $timeStamp = Carbon::now()->toDateTimeString();
        return $timeStamp;
    }
}
