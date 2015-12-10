zf2-cache-mongo
===============

ZF2 cache storage compatible library using MongoDB's TTL collection

Overview
===============
It seems that there aren't that many people out there who is interested in creating a ZF2 compatible cache storage backend library for MongoDB, hence this project. 

The library utilizes  the Time To Live (TTL) collection feature introduced in MongoDB v2.2

Using the Library
================
   1. Update your `composer.json` (if you have)
   
        (to be added)        

   2. Instantiate the library 
       
        $options = array(
          'dsn' => 'mongodb://127.0.0.1',
          'mongoOptions' => array(/* any of the valid \Mongo or \MongoClient options */),
          'dbname' => 'cachedb',
          'collection' => 'cache',
          'ttl' => 10,
          'namespace' => 'stl'
        );

        $mongoCache = new \Juneym\Cache\Storage\Adapter\Mongo($options);
        $cacheKey = md5('This is a sample key');
        
        $created = $mongoCache->setItem($cacheKey, array('x' => 12345, 'y' => 'ABCDEF' . rand(0,10000)));
        if (!$created) {
            die("Cached using key: " . $cacheKey . "\n");
        } 
        
        $data = $mongoCache->getItem($cacheKey);
        print_r($data);
        unset($mongoCache);


About TTL Index & Cache Expiry
================
There are two ways a cached data will expire. 

   1. When the difference between the current time and the cache item's `created` time is more than the cache item's `ttl` value (in seconds)
   2. When the record's `created` value is way past the MongoDB's cache collection TTL index (`expireAfterSeconds`). Note that MongoDB's garbage collection runs every 60 seconds so don't be surprised if the cached item is still available. MongoDB's garbage collector will eventually remove all qualified records in the background. 

Required Index
================
Assuming that the cache database is called "cachedb" and the collection name is "cache", fhe following
indexes are required:

    use cachedb
    db.cache.ensureIndex({ns:1}, {background:true});
    db.cache.ensureIndex({ns:1, key:1}, {background:true});
    db.cache.ensureIndex({ns:1, tags:1}, {background:true});
    

If you are using version below v0.2.0, please use the following TTL index definition:

    db.cache.ensureIndex({created:1}, {background:true, expireAfterSeconds: 3600, name: 'colRecordTTl'});

Starting in v0.2.0, the `expireAt` field is populated based on the `ttl` value at the time the cache entry has been created (or saved). Use the following index to enforce automatic expiration of record based on `expireAt` field.

    db.cache.ensureIndex({created:1, expireAt:1}, {background: true});
    db.cache.ensureIndex({expireAt:1}, {expireAfterSeconds: 0, name: 'cache_expire_at'});


