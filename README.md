zf2-cache-mongo
===============

ZF2 cache storage compatible library using MongoDB's TTL collection

Overview
===============
It seems that there aren't that many people out there who is interested in creating a ZF2 compatible cache storage backend library for MongoDB, hence this project. 

The library utilizes  the Time To Live (TTL) collection feature introduced in MongoDB v2.2


Required Index
================
Assuming that the cache database is called "cachedb" and the collection name is "cache", fhe following
indexes are required:

    use cachedb
    db.cache.ensureIndex({ns:1}, {background:true});
    db.cache.ensureIndex({ns:1, key:1}, {background:true});
    db.cache.ensureIndex({created:1}, {background:true, expireAfterSeconds: 3600, name: 'colRecordTTl'});

