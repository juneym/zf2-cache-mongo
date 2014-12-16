<?php

namespace Juneym\Cache\Storage\Adapter;

use Zend\Cache\Exception;
use Zend\Cache\Storage\Adapter;

/**
 * These are options that are specific to the Mongo cache adapter
 */
class MongoOptions extends Adapter\AdapterOptions
{

    protected $dbname;
    protected $dsn;
    protected $collection;
    protected $mongoOptions = array();

    /**
     * Set the value for 'mongoOptions'
     *
     * @param $value
     * @return MongoOptions
     */
    public function setMongoOptions(array $value)
    {
        $this->mongoOptions = $value;
        return $this;
    }

    /**
     * Get the va;ie for 'mongoOptions'
     *
     * @return array
     */
    public function getMongoOptions()
    {
        return $this->mongoOptions;
    }



    /**
     * Set the database name
     *
     * @param $value
     * @return MongoOptions
     */
    public function setDbname($value)
    {
        $this->dbname = $value;
        return $this;
    }

    /**
     * Get the database name
     *
     * @return string
     */
    public function getDbname()
    {
        return $this->dbname;
    }


    /**
     * Set the collection name
     *
     * @param $value
     * @return MongoOptionsSet the database name
     */
    public function setCollection($value)
    {
        $this->collection = $value;
        return $this;
    }

    /**
     * @return string
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * Set the DSN option
     *
     * @param $value
     * @return MongoOptions
     */
    public function setDsn($value)
    {
        $this->dsn = $value;
        return $this;
    }
    /**
     * Get the dsn config value
     *
     * @return string
     */
    public function getDsn()
    {
        return $this->dsn;
    }

}
