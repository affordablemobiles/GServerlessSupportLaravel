<?php

namespace A1comms\GaeSupportLaravel\Session;

use GDS;
use Carbon\Carbon;
use SessionHandlerInterface;
use A1comms\GaeSupportLaravel\Storage\MemcacheContainer;

/**
 * class DataStoreSessionHandler
 *
 * @uses SessionHandlerInterface
 *
 * @package A1comms\GaeSupportLaravel\Session
 */
class DataStoreSessionHandler implements SessionHandlerInterface
{
    /**
     * $SESSION_PREFIX
     *
     * @var string
     *
     * @access private
     */
    private $SESSION_PREFIX;

    /**
     * $expire
     *
     * @var mixed
     *
     * @access private
     */
    private $expire;

    /**
     * $memcacheContainer
     *
     * @var mixed
     *
     * @access private
     */
    private $memcacheContainer;

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
     * __construct
     *
     * @access public
     *
     * @return mixed Value.
     */
    public function __construct()
    {
        $this->SESSION_PREFIX = '_' . gae_project() . '_' . gae_service() . '_sess_';

        $this->memcacheContainer = new MemcacheContainer();

        // Get session max lifetime to leverage Memcache expire functionality.
        $this->expire = ini_get("session.gc_maxlifetime");
        $this->lastaccess = $this->getTimeStamp();
        $this->deleteTime = Carbon::now()->subDay()->toDateTimeString();

        $obj_gateway_one = false;
        if ( is_gae_std() )
        {
            $obj_gateway_one = new \GDS\Gateway\ProtoBuf(null, null);
        }
        else
        {
            $obj_gateway_one = new \GDS\Gateway\RESTv1(gae_project(), null);
        }

        $this->obj_schema = (new GDS\Schema('sessions'))
            ->addString('data', false)
            ->addDateTime('lastaccess');

        $this->obj_store = new GDS\Store($this->obj_schema, $obj_gateway_one);
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
        return $this->memcacheContainer->close();
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

        $id = $this->SESSION_PREFIX.$id;

        $mdata = $this->memcacheContainer->get($id);
        if ($mdata !== false){
            $this->orig_data = $mdata;
            return $mdata;
        }

        $obj_sess = $this->obj_store->fetchByName($id);

        if($obj_sess instanceof GDS\Entity) {
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
        $id = $this->SESSION_PREFIX.$id;
        $result = $this->memcacheContainer->set($id, $data, $this->expire);

        $obj_sess = $this->obj_store->createEntity([
            'data'          => $data,
            'lastaccess'    => $this->lastaccess
        ])->setKeyName($id);

        if ($this->orig_data != $data){
            $this->obj_store->upsert($obj_sess);
        }

        return $result;
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
        $id = $this->SESSION_PREFIX.$id;

        $result = $this->memcacheContainer->delete($id);

        $obj_sess = $this->obj_store->fetchByName($id);

        if($obj_sess instanceof GDS\Entity) {
            $this->obj_store->delete($obj_sess);
        }

        return $result;
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
        $rowCount = 0;
        do {
            $arr = $this->obj_store->fetchAll("SELECT * FROM sessions WHERE lastaccess < @old", ['old' => $this->deleteTime]);
            $rowCount = count($arr);
            syslog(LOG_INFO, 'Found '.$rowCount.' records');

            if (!empty($arr)) {
                $this->obj_store->delete($arr);
            }
        } while ($rowCount > 0);
    }

    private function getTimeStamp()
    {
        $timeStamp = Carbon::now()->toDateTimeString();
        return $timeStamp;
    }
}
