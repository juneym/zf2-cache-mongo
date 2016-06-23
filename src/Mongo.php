<?php

namespace Juneym\Cache\Storage\Adapter;

use StdClass;
use Exception as BaseException;
use Zend\Cache\Exception;
use Zend\Cache\Storage;
use Zend\Stdlib\ErrorHandler;
use Zend\Cache\Storage\Adapter;
use Zend\EventManager;

class Mongo extends Adapter\AbstractAdapter implements
    Storage\FlushableInterface,
    Storage\ClearByNamespaceInterface,
    TaggableInterface
{


    /**
     * @var \MongoClient
     */
    protected $connection = null;

    /**
     * @var \MongoDB
     */
    protected $db;

    /**
     * @var \MongoCollection
     */
    protected $collection;

    /**
     * @var string
     */
    protected $namespace = 'defaultNs';
    
    /**
     * Flag for allowing the library to throw exceptions for debugging purposes.
     * 
     * @var boolean
     */
    protected $throwExceptions = true;

    /**
     * Cache Item metadata attributes
     *
     * @var array
     */
    protected $cacheItemAttributes = array();

    /**
     * Constructor
     *
     * @param  array $options
     * @throws Exception\ExceptionInterface
     */
    public function __construct($options = null)
    {
        parent::__construct($options);
    }

    /**
     * Destructor
     */
    public function __destruct() {
        if (!empty($this->connection)) {
            $this->connection->close(TRUE);
        }
        parent::__destruct();
    }

    /**
     * Set options
     *
     * @param  array|Traversable|MongoOptions $options
     * @return Memcached
     * @see    getOptions()
     */
    public function setOptions($options)
    {
        if (!$options instanceof MongoOptions) {
            $options = new MongoOptions($options);
        }

        return parent::setOptions($options);
    }

    /**
     * Enable or disable throwing of exceptions 
     * 
     * @param type $value
     */
    public function setThrowExceptions($value) 
    {
        $this->throwExceptions = ($value == true) ? true: false;
    }
    
    /**
     * Initialize the internal
     *
     * @return \MongoClient
     */
    protected function initMongo()
    {

        if (!empty($this->connection)) {
            return $this->connection;
        }

        $options = $this->getOptions();

        $dsn = $options->dsn;
        $dbname = $options->dbname;
        $collection = $options->collection;
        if (empty($dsn) || empty($dbname) || empty($collection)) {
            throw new Exception\InvalidArgumentException('The "dsn", "dbname", "collection" configurations are missing', 2);
        }

        $this->namespace = $options->getNamespace();

        $_options = $options->mongoOptions;

	if (!class_exists('\MongoClient')) {
           $this->connection = new \Mongo($dsn, $_options);
        } else { 
           $this->connection = new \MongoClient($dsn, $_options);
        }
        $this->db     = $this->connection->selectDB($dbname);
        $this->collection = $this->connection->selectCollection($this->db, $collection);
    }


    /**
     * Flush the whole storage
     *
     * @return bool
     */
    public function flush()
    {
        $this->initMongo();
        $result = $this->collection->remove(array(), array('w' => 1));
        if (is_array($result) && ($result['ok'] != 1)) {
            throw new Exception(
                sprintf("Error: %s  Err: %s", $result['errmsg'], $result['err']),
                $result['code']);
        }

        return true;
    }


    /**
     * Internal method to get an item.
     *
     * @param  string  $normalizedKey
     * @param  bool $success
     * @param  mixed   $casToken
     * @return mixed Data on success, null on failure
     * @throws Exception\ExceptionInterface
     */
    protected function internalGetItem(& $normalizedKey, & $success = null, & $casToken = null)
    {
        
        try {
            $this->initMongo();

            $data = $this->collection->findOne(
                array(
                    'ns' => $this->namespace,
                    'key' => $normalizedKey
            ));


            $current = new \MongoDate();

            $success = true;
            if (empty($data)) {
                $success = false;
                return $data;
            } elseif (is_array($data) && ($data['ttl'] > 0) &&
                        (($current->sec - $data['created']->sec) > $data['ttl'])) {

                 $this->getEventManager()->trigger(
                     new Storage\PostEvent(
                     'onCacheItem.expired.ttl',
                     $this,
                     new \ArrayObject(func_get_args()),
                    $data)
                 );
                 
                 $data = null; //force expire
                $success = false;

            }

            if ($success && isset($data['data']) && is_object($data['data']) && ($data['data'] instanceof \MongoBinData)) {
                 $data['data'] = unserialize($data['data']->bin);
            }

            return $data;
        } catch (\MongoException $e) {
            
            if ($this->throwExceptions) {
                throw $e;
            }
            $success = false;
            return null;
        } 
    }

    /**
     * Internal method to store an item.
     *
     * @param  string $normalizedKey
     * @param  mixed  $value
     * @return bool
     * @throws Exception\ExceptionInterface
     */
    protected function internalSetItem(& $normalizedKey, & $value)
    {
        try {
            $this->initMongo();

            $currentDtm = new \DateTime("now");
            $expireDtm  = clone $currentDtm;
            $ttl = abs($this->getOptions()->getTtl());
            $expireDtm  = $expireDtm->add(new \DateInterval("PT" . $ttl . "S"));

            $cacheRecord = array(
                'key'  => $normalizedKey,
                'ns'   => $this->getOptions()->getNamespace(),
                'data' => new \MongoBinData(serialize($value), \MongoBinData::GENERIC),
                'ttl'  => $ttl,
                'tags' => array(),
                'created' => new \MongoDate($currentDtm->getTimestamp()),
                'expireAt' => new \MongoDate($expireDtm->getTimestamp())
            );

            $itemAttr = $this->getCacheItemAttributes();
            if (!empty($itemAttr)) {
                $cacheRecord['attr'] = $itemAttr;
                $this->setCacheItemAttributes(array());
            }


            $result = $this->collection->update(
                        array(
                            'key' => $normalizedKey,
                            'ns' => $this->getOptions()->getNamespace()
                        ),
                        $cacheRecord,
                        array(
                            'upsert' => true,
                            'w' => 1
                        )
                    );

            unset($expireDtm);
            unset($currentDtm);
            unset($ttl);
            unset($cacheRecord);
            if ($this->throwExceptions && is_array($result) && ($result['ok'] != 1)) {
                throw new Exception(
                    sprintf("Error: %s  Err: %s", $result['errmsg'], $result['err']),
                    $result['code']);
                
            }
        } catch (\MongoException $e) {
            if ($this->throwExceptions) {
                throw $e;
            }
            return false;
        } 
            
        return true;
    }

    /**
     * Internal method to remove an item.
     *
     * @param  string $normalizedKey
     * @return bool
     * @throws Exception\ExceptionInterface
     */
    protected function internalRemoveItem(& $normalizedKey)
    {
        try {
            
            $this->initMongo();
            $result = $this->collection->remove(
                array(
                    'key' => $normalizedKey,
                    'ns' => $this->getOptions()->getNamespace()
                ),
                array('w' => 1 )
            );

            if ($this->throwExceptions && is_array($result) && ($result['ok'] != 1)) {
                throw new Exception(
                    sprintf("Error: %s  Err: %s", $result['errmsg'], $result['err']),
                    $result['code']);
            }
        } catch (\MongoException $e) {
            if ($this->throwExceptions) {
                throw $e;
            }
            return false;
        } 
        
        return true;
    }


    /**
     * Remove items by given namespace
     *
     * @param string $namespace
     * @return bool
     */
    public function clearByNamespace($namespace)
    {
        if ($namespace === '') {
            throw new Exception\InvalidArgumentException('No namespace given');
        }

        try {
            $this->initMongo();

            $result = $this->collection->remove(
                array('ns' => $namespace),
                array('w' => 1)
            );

            if ($this->throwExceptions && is_array($result) && ($result['ok'] != 1)) {
                throw new Exception(
                    sprintf("Error: %s  Err: %s", $result['errmsg'], $result['err']),
                    $result['code']);
            }
        } catch (\MongoException $e) {
            if ($this->throwExceptions) {
                throw $e;
            }
            return false;
        } 

        return true;
    }


    /**
     * Mark a cache item as expired to ensure that it becomes available
     * for offline garbage collection
     *
     * @param string $normalizedKey the cache key
     */
    public function markItemAsExpired($normalizedKey)
    {
        try {
            $this->initMongo();
            $result = $this->collection->update(
                array(
                    'ns' => $this->getOptions()->getNamespace(),
                    'key' => $normalizedKey
                ),
                array(
                    '$set' => array(
                        'key'  => "expired_" . microtime(true) . "_" . $normalizedKey,
                        'expireAt' => new \MongoDate(),
                        'expired' => true
                    )
                ),
                array('w' => 1)
            );

            if ($this->throwExceptions && is_array($result) && ($result['ok'] != 1)) {
                throw new Exception(
                    sprintf("Error: %s  Err: %s", $result['errmsg'], $result['err']),
                    $result['code']);
            }
        } catch (\MongoException $e) {
            if ($this->throwExceptions) {
                throw $e;
            }
            return false;
        } 
        return true;
    }

    /**
     * Set tags to an item by given key.
     * An empty array will remove all tags.
     *
     * @param string   $key
     * @param string[] $tags
     * @return bool
     */
    public function setTags($key, array $tags)
    {
        try {
            $this->initMongo();

            $currentDtm = new \DateTime("now");
            $expireDtm  = clone $currentDtm;
            $ttl = abs($this->getOptions()->getTtl());
            $expireDtm  = $expireDtm->add(new \DateInterval("PT" . $ttl . "S"));

            $result = $this->collection->update(
                array(
                    'key' => $key,
                    'ns' => $this->getOptions()->getNamespace()
                ),
                array(
                    '$set' => array(
                        'tags' => $tags,
                        'created' => new \MongoDate($currentDtm->getTimestamp()),
                        'expireAt' => new \MongoDate($expireDtm->getTimestamp())
                    )
                ),
                array(
                    'w' => 1
                )
            );

            unset($currentDtm);
            unset($expireDtm);
            unset($ttl);
            if ($this->throwExceptions && is_array($result) && ($result['ok'] != 1)) {
                throw new Exception(
                    sprintf("Error: %s  Err: %s", $result['errmsg'], $result['err']),
                    $result['code']);
            }
        } catch (\MongoException $e) {
            if ($this->throwExceptions) {
                throw $e;
            }
            return false;
        }             

        return true;
    }

    /**
     * Get tags of an item by given key
     *
     * @param string $key
     * @return string[]|FALSE
     */
    public function getTags($key)
    {
        $data = $this->getItem($key);
        $tags = false;
        if (!empty($data) && is_array($data))
        {
            $tags = isset($data['tags']) ? $data['tags'] : $tags;
        }

        return $tags;
    }

    /**
     * Remove items matching given tags.
     *
     * If $disjunction only one of the given tags must match
     * else all given tags must match.
     *
     * @param string[] $tags
     * @param  bool  $disjunction
     * @return bool
     */
    public function clearByTags(array $tags, $disjunction = false)
    {
        try {
            
            $this->initMongo();

            if ($disjunction === true) {
                $tagCriteria = array('$in' => $tags);
            } else {
                $tagCriteria = array('$all' => $tags);
            }

            $criteria = array(
                'ns' => $this->getOptions()->getNamespace(),
                'tags' => $tagCriteria
            );


            $result = $this->collection->remove(
                $criteria,
                array('w' => 1)
            );

            if ($this->throwExceptions && is_array($result) && ($result['ok'] != 1)) {
                throw new Exception(
                    sprintf("Error: %s  Err: %s", $result['errmsg'], $result['err']),
                    $result['code']);
            }
            
        } catch (\MongoException $e) {
            if ($this->throwExceptions) {
                throw $e;
            }
            return false;
        }             

        return true;
    }


    /**
     * Return items matching given tags.
     *
     * If $disjunction only one of the given tags must match
     * else all given tags must match.
     *
     * @param string[] $tags
     * @param  bool  $disjunction
     * @return Iterator|bool|null
     */
    public function getByTags(array $tags,  $disjunction = false)
    {
        
        try {
            
            $this->initMongo();

            if ($disjunction === true) {
                $tagCriteria = array('$in' => $tags);
            } else {
                $tagCriteria = array('$all' => $tags);
            }

            $criteria = array(
                'ns' => $this->getOptions()->getNamespace(),
                'tags' => $tagCriteria
            );

            $cursor = $this->collection->find(
                $criteria
            );

            if ($cursor->count() <= 0) {
                return null;
            }
        } catch (\MongoException $e) {
            if ($this->throwExceptions) {
                throw $e;
            }
            return null;
        }             

        return $cursor;
    }


    /**
     * This method provides a way of attaching attributes to individual cache item on setItem().
     *
     * The value for cacheItemAttributes property is cleared after a successful setItem() call.
     *
     * @param array $attr   An array of key/value pair. Note that the key must not include characters the violates
     * MongoDB's token namespace e.g. key with dollar ($) character will cause an error for sure.
     *
     * @return Mongo
     */
    public function setCacheItemAttributes(array $attr) {
        $this->cacheItemAttributes = $attr;
        return $this;
    }

    /**
     * Return the current cacheItemAttribute
     *
     * @return array
     */
    public function getCacheItemAttributes()
    {
        return $this->cacheItemAttributes;
    }

}
