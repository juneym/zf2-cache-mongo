<?php

namespace test\integration;

use \Juneym\Cache\Storage;

class MongoStorageAdapterTest extends \PHPUnit_Framework_TestCase
{
    protected $options = array(
        'dsn' => 'mongodb://127.0.0.1',
	'mongoOptions' => array(),
        'dbname' => 'cachedb',
        'collection' => 'cache',
        'ttl' => 10,
        'namespace' => 'stl'
    );


    function testCanCacheData()
    {
        $mongoCache = new Storage\Adapter\Mongo($this->options);

        $cacheKey = md5('this is a test key');
        $result = $mongoCache->setItem($cacheKey, array('x' => 12345, 'y' => 'ABCDEF' . rand(0,10000)));
        $this->assertTrue($result);
        unset($mongoCache);
    }
    
    /**
     * @depends testCanCacheData
     */
    function testCanReadCachedData()
    {
        $mongoCache = new Storage\Adapter\Mongo($this->options);

        $cacheKey = md5('this is a test key');

        $result0 = $mongoCache->hasItem($cacheKey);
        $this->assertTrue($result0);

        $result = $mongoCache->getItem($cacheKey);
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('ns', $result);
        $this->assertArrayHasKey('tags', $result);
        $this->assertArrayHasKey('created', $result);
        $this->assertArrayHasKey('expireAt', $result);
        $this->assertTrue($result['created']->sec < $result['expireAt']->sec);

        $this->assertArrayHasKey('ttl', $result);
        $this->assertEquals(10, $result['ttl']);

        $this->assertArrayHasKey('data', $result);
        $this->assertEquals(12345, $result['data']['x']);

        unset($mongoCache);
    }

    /**
     * @depends testCanReadCachedData
     */
    function testCanManuallyRemoveCachedData()
    {
        $mongoCache = new Storage\Adapter\Mongo($this->options);

        $cacheKey = md5('this is a test key');

        $result1 = $mongoCache->getItem($cacheKey);
        $this->assertNotNull($result1);
        $this->assertTrue(is_array($result1));
        $this->assertEquals(12345, $result1['data']['x']);

        $result2 = $mongoCache->removeItem($cacheKey);
        $this->assertTrue($result2);

        $result3 = $mongoCache->getItem($cacheKey);
        $this->assertNull($result3);

        unset($mongoCache);
    }


    /**
     * @depends testCanReadCachedData
     */
    public function testCanExpireViaCacheTTL()
    {
        $options = $this->options;
        $options['ttl'] = 3; //5-secs expiration
        $mongoCache = new Storage\Adapter\Mongo($options);

        $cacheKey = md5('some key for ttl test');

        $result = $mongoCache->setItem(
            $cacheKey,
            array('x' => 12345, 'y' => 'ASDF')
        );
        $this->assertTrue($result);

        $exist1 = $mongoCache->hasItem($cacheKey);
        $this->assertTrue($exist1);

        sleep(4);

        //should expire after the defined 'ttl' of 3 seconds
        $exist2 = $mongoCache->hasItem($cacheKey);
        $this->assertFalse($exist2);

        unset($mongoCache);
    }

    /**
     * @depends testCanReadCachedData
     */
    public function testCanDisableTTLExpiration()
    {
        $options = $this->options;
        $options['ttl'] = 0; //disable ttl-based expiration
        $mongoCache = new Storage\Adapter\Mongo($options);

        $cacheKey = md5('some key for ttl-zero test');

        $result = $mongoCache->setItem(
            $cacheKey,
            array('x' => 12345, 'y' => 'ASDF')
        );
        $this->assertTrue($result);

        $exist1 = $mongoCache->hasItem($cacheKey);
        $this->assertTrue($exist1);

        sleep(2);

        //should not expire based on ttl.
        $exist2 = $mongoCache->hasItem($cacheKey);
        $this->assertTrue($exist2);

        unset($mongoCache);
    }

    public function testCanFlushTheStorage()
    {
        $options1 = $this->options;
        $options1['namespace'] = 'ns1';
        $mongoCache1 = new Storage\Adapter\Mongo($options1);

        $cacheKey1 = md5('record number 1');
        $result1 = $mongoCache1->setItem(
            $cacheKey1,
            array('x' => 123451234, 'y' => 'ASDFEFG_' . __METHOD__)
        );
        $this->assertTrue($result1);

        $exist1 = $mongoCache1->hasItem($cacheKey1);
        $this->assertTrue($exist1);

        $options2 = $this->options;
        $options2['namespace'] = 'ns2';
        $mongoCache2 = new Storage\Adapter\Mongo($options2);

        $cacheKey2 = md5('record number 2');
        $result2 = $mongoCache2->setItem(
            $cacheKey2,
            array('x' => 123451234, 'y' => 'ASDFEFG_' . __METHOD__)
        );
        $this->assertTrue($result2);

        $exist2 = $mongoCache2->hasItem($cacheKey2);
        $this->assertTrue($exist2);

        $mongoCache1->flush();
        $this->assertFalse($mongoCache1->hasItem($cacheKey1));
        $this->assertFalse($mongoCache2->hasItem($cacheKey2));

        unset($mongoCache1);
        unset($mongoCache2);
    }

    /**
     * @depends testCanFlushTheStorage
     */
    public function testCanClearByNamespace()
    {
        $options1 = $this->options;
        $options1['namespace'] = 'ns1';
        $mongoCache1 = new Storage\Adapter\Mongo($options1);

        $cacheKey1 = md5('record number 1');
        $result1 = $mongoCache1->setItem(
            $cacheKey1,
            array('x' => 123451234, 'y' => 'ASDFEFG_' . __METHOD__)
        );
        $this->assertTrue($result1);

        $exist1 = $mongoCache1->hasItem($cacheKey1);
        $this->assertTrue($exist1);

        $options2 = $this->options;
        $options2['namespace'] = 'ns2';
        $mongoCache2 = new Storage\Adapter\Mongo($options2);

        $cacheKey2 = md5('record number 2');
        $result2 = $mongoCache2->setItem(
            $cacheKey2,
            array('x' => 123451234, 'y' => 'ASDFEFG_' . __METHOD__)
        );
        $this->assertTrue($result2);

        $mongoCache1->clearByNamespace('ns1');

        $exist2 = $mongoCache2->hasItem($cacheKey2);
        $this->assertTrue($exist2);

        $this->assertFalse($mongoCache1->hasItem($cacheKey1));
        $this->assertTrue($mongoCache2->hasItem($cacheKey2));

        $mongoCache1->clearByNamespace('ns2');
        $this->assertFalse($mongoCache2->hasItem($cacheKey2));

        unset($mongoCache1);
        unset($mongoCache2);
    }


    /**
     * @depends testCanClearByNamespace
     * @depends testCanFlushTheStorage
     */
    public function testCanAssociateTagsToCacheItem()
    {
        $mongoCache = new Storage\Adapter\Mongo($this->options);

        $pageUrl = 'http://example.com/search/side+mirrors';
        $cacheKey = md5($pageUrl);
        $result = $mongoCache->setItem(
            $cacheKey,
            array(
                'page' => $pageUrl,
                'site' => 'example.com',
                'products' => array(
                    array(
                        'recordId' => 123456,
                        'sku' => 'MIR12345',
                        'price' => 20.00,
                        'currency' => 'USD'
                    ),
                    array(
                        'recordId' => 123459,
                        'sku' => 'MIR12349',
                        'price' => 19.00,
                        'currency' => 'USD'
                    )
                 )
            )
        );
        $this->assertTrue($result);

        $tagResult = $mongoCache->setTags($cacheKey, array('123456', '123459'));
        $this->assertTrue($tagResult);

        $cachedItem = $mongoCache->getItem($cacheKey);
        $this->assertNotEmpty($cachedItem);
        $this->assertInternalType('array', $cachedItem);
        $this->assertArrayHasKey('tags', $cachedItem);
        $this->assertCount(2, $cachedItem['tags']);

        $this->assertContains('123456', $cachedItem['tags']);
        $this->assertContains('123459', $cachedItem['tags']);

        unset($mongoCache);
    }


    /**
     * @depends testCanAssociateTagsToCacheItem
     */
    public function testCanRetrieveCacheTags()
    {
        $mongoCache = new Storage\Adapter\Mongo($this->options);

        $pageUrl = 'http://example.com/search/side+mirrors';
        $cacheKey = md5($pageUrl);

        $tags = $mongoCache->getTags($cacheKey);

        $this->assertInternalType('array', $tags);
        $this->assertCount(2, $tags);

        $this->assertContains('123456', $tags);
        $this->assertContains('123459', $tags);

        unset($mongoCache);
    }

    /**
     * @depends testCanRetrieveCacheTags
     */
    public function testCanSetTheTagsToEmpty()
    {
        $mongoCache = new Storage\Adapter\Mongo($this->options);

        $pageUrl = 'http://example.com/search/side+mirrors';
        $cacheKey = md5($pageUrl);
        $tagResult = $mongoCache->setTags($cacheKey, array());
        $this->assertTrue($tagResult);

        $tags = $mongoCache->getTags($cacheKey);

        $this->assertInternalType('array', $tags);
        $this->assertCount(0, $tags);

        $mongoCache->removeItem($cacheKey);
        unset($mongoCache);
    }

    public function testCanGetCachedItemsByTags()
    {
        $data = array(
            'cacheKey1234' => array(
                'data' => array(
                    'page' => 'http://example.com/pages/right+mirror',
                    'site' => 'example.com',
                    'products' => array(
                        array(
                            'recordId' => 123456,
                            'sku' => 'MIR12345',
                            'price' => 20.00,
                            'currency' => 'USD'
                        ),
                        array(
                            'recordId' => 123459,
                            'sku' => 'MIR12349',
                            'price' => 19.00,
                            'currency' => 'USD'
                        )
                    )
                ),
                'tags' => array(123459,123456)
            ),

            'cacheKey245123' => array(
                'data' => array(
                    'page' => 'http://example.com/pages/right+mirror',
                    'site' => 'example.com',
                    'products' => array(
                        array(
                            'recordId' => 99123,
                            'sku' => 'MIR99123',
                            'price' => 22.00,
                            'currency' => 'USD'
                        ),
                        array(
                            'recordId' => 99813,
                            'sku' => 'MIR99813',
                            'price' => 15.00,
                            'currency' => 'USD'
                        )
                    )
                ),
                'tags' => array(99123,99813)
            ),

            'cacheKey233212' => array(
                'data' => array(
                    'page' => 'http://example.com/pages/top+mirrors',
                    'site' => 'example.com',
                    'products' => array(
                        array(
                            'recordId' => 8891,
                            'sku' => 'MIR8891',
                            'price' => 22.00,
                            'currency' => 'USD'
                        ),
                        array(
                            'recordId' => 8892,
                            'sku' => 'MIR8892',
                            'price' => 15.00,
                            'currency' => 'USD'
                        )
                    )
                ),
                'tags' => array(8891,8892)
            )

        );


        $mongoCache = new Storage\Adapter\Mongo($this->options);

        foreach ($data as $cacheKey => $_data) {
            $result = $mongoCache->setItem($cacheKey, $_data['data']);
            $this->assertTrue($result);
            $tagResult = $mongoCache->setTags($cacheKey, $_data['tags']);
            $this->assertTrue($tagResult);
        }

        //retrieve a record with specific tags
        $records = $mongoCache->getByTags(array(8891,8892));
        $this->assertNotNull($records);
        $this->assertTrue($records instanceof \MongoCursor);
        $this->assertTrue($records instanceof \Iterator);
        $this->assertEquals(1, $records->count());

        $data = $records->getNext();
        $this->assertEquals('cacheKey233212', $data['key']);

        //retrieve records matching any of the tags provided
        $records = $mongoCache->getByTags(array(8891,99813,123456), true);
        $this->assertEquals(3, $records->count());

        unset($mongoCache);
    }


    /**
     * @depends testCanGetCachedItemsByTags
     * @see testCanGetCachedItemsByTags
     */
    public function testCanClearByTheTagsToEmpty()
    {
        $mongoCache = new Storage\Adapter\Mongo($this->options);


        //try remove cacheKey233212 (all tags are required, 123456 is an invalid tag)
        $mongoCache->clearByTags(array(8892,8891,123456));
        $record = $mongoCache->getItem('cacheKey233212');
        $this->assertNotNull($record);
        $this->assertEquals('cacheKey233212', $record['key']);

        //try remove cacheKey233212 (all tags are required, exact match)
        $mongoCache->clearByTags(array(8892,8891));
        $record = $mongoCache->getItem('cacheKey233212');
        $this->assertNull($record);

        //try to test the disjunction parameter
        //notes: "99123" is one of tags for "cacheKey245123"
        //       "123456" is one of tags for "cacheKey1234"
        $mongoCache->clearByTags(array(99123,123456), true);
        $record1 = $mongoCache->getItem('cacheKey245123');
        $record2 = $mongoCache->getItem('cacheKey1234');
        $this->assertNull($record1);
        $this->assertNull($record2);

        unset($mongoCache);
    }
    
    /**
     * @test
     * @expectedException \MongoConnectionException
     */
    public function testShouldThrowAConnectionExceptionByDefault()
    {
        $options = array(
            'dsn' => 'mongodb://127.0.0.1:27013',
            'mongoOptions' => array(),
            'dbname' => 'cachedb',
            'collection' => 'cache',
            'ttl' => 10,
            'namespace' => 'stl'
        );
        
        $mongoCache = new Storage\Adapter\Mongo($options);
        $cacheKey = md5('this is a test key');
        $result0 = $mongoCache->hasItem($cacheKey);
        $this->assertFalse($result0);
    }
    
    /**
     * @test
     */
    function testCanRecoverWhenMongoDbConnectionIsNotPossible()
    {
        
        $options = array(
            'dsn' => 'mongodb://127.0.0.1:27013',
            'mongoOptions' => array(),
            'dbname' => 'cachedb',
            'collection' => 'cache',
            'ttl' => 10,
            'namespace' => 'stl'
        );
        
        $mongoCache = new Storage\Adapter\Mongo($options);
        $mongoCache->setThrowExceptions(false);

        $cacheKey = md5('this is a test key');

        $result0 = $mongoCache->hasItem($cacheKey);
        $this->assertFalse($result0);

        $result1 = $mongoCache->getItem($cacheKey);
        $this->assertNull($result1);
        
        $result2 = $mongoCache->setItem($cacheKey, 'some_value');
        $this->assertFalse($result2);
        
        unset($mongoCache);
    }


    /**
     * @throws \MongoException
     * @throws \Zend\Cache\Exception
     */
    public function testCanMarkItemAsExpired()
    {
        $mongoCache = new Storage\Adapter\Mongo($this->options);

        $cacheKey = md5('this is a test key' . __METHOD__);
        $result = $mongoCache->setItem($cacheKey, array('x' => 11111, 'y' => 'ABCDEF' . rand(0,10000)));
        $this->assertTrue($result);
        $mongoCache->markItemAsExpired($cacheKey);
        sleep(10);
        $data = $mongoCache->getItem($cacheKey);
        $this->assertNull($data);
        unset($mongoCache);
    }
    
}
