<?php

namespace Juneym\Cache\Storage\Adapter;

use StdClass;
use Exception as BaseException;
use Zend\Cache\Exception;
use Zend\Cache\Storage;
use Zend\Stdlib\ErrorHandler;
use Zend\Cache\Storage\Adapter;

class Mongo extends Adapter\AbstractAdapter implements
    Storage\FlushableInterface
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
     * Constructor
     *
     * @param  array $options
     * @throws Exception\ExceptionInterface
     */
    public function __construct($options = null)
    {
        if (version_compare('1.4.4', phpversion('mongo')) > 0) {
            throw new Exception\ExtensionNotLoadedException("Missing ext/mongo version >= 1.4.4");
        }

        parent::__construct($options);
    }

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
        $this->connection = new \MongoClient($dsn);
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
        $result = $this->collection->remove(
                    array('ns' => $this->namespace),
                    array('w' => 1)
                );

        if (is_array($result) && ($result['ok'] != 1)) {
            throw new Exception(
                sprintf("Error: %s  Err: %s", $result['errmsg'], $result['err']),
                $result['code']);
        }
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
        $this->initMongo();

        try {

            $data = $this->collection->findOne(
                array(
                    'ns' => $this->namespace,
                    'key' => $normalizedKey
            ));

            $current = new \MongoDate();

            $success = true;

            if (is_array($data) &&
                (($current->sec - $data['created']->sec) > $data['ttl'])) {
                $data = null; //force expire
                $success = false;
            }

            return $data;
        } catch (\Exception $e) {
            $success = false;
            throw $e;
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
        $this->initMongo();
        $result = $this->collection->update(
                    array(
                        'key' => $normalizedKey,
                        'ns' => $this->getOptions()->getNamespace()
                    ),
                    array(
                        'key'  => $normalizedKey,
                        'ns'   => $this->getOptions()->getNamespace(),
                        'data' => $value,
                        'ttl'  => $this->getOptions()->getTtl(),
                        'tags' => array(),
                        'created' => new \MongoDate()
                    ),
                    array(
                        'upsert' => true,
                        'w' => 1
                    )
                );

        if (is_array($result) && ($result['ok'] != 1)) {
            throw new Exception(
                sprintf("Error: %s  Err: %s", $result['errmsg'], $result['err']),
                $result['code']);
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
        $this->initMongo();
        $result = $this->collection->remove(
            array(
                'key' => $normalizedKey,
                'ns' => $this->getOptions()->getNamespace()
            ),
            array('w' => 1 )
        );

        if (is_array($result) && ($result['ok'] != 1)) {
            throw new Exception(
                sprintf("Error: %s  Err: %s", $result['errmsg'], $result['err']),
                $result['code']);
        }
        return true;
    }


}