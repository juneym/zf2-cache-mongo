<?php

namespace test\integration;

use \Juneym\Cache\Storage;

class MongoStorageAdapterTest extends \PHPUnit_Framework_TestCase
{
    protected $options = array(
        'dsn' => 'mongodb://127.0.0.1',
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

        $this->assertArrayHasKey('ttl', $result);
        $this->assertEquals(10, $result['ttl']);

        $this->assertArrayHasKey('data', $result);
        $this->assertEquals(12345, $result['data']['x']);

        unset($mongoCache);
    }

    /**
     * @depends testCanReadCachedData
     */
    function testCanManuallyExpireCachedData()
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

        //should expire after the defined 'ttl' of 3 seconds
        $exist2 = $mongoCache->hasItem($cacheKey);
        $this->assertTrue($exist2);
    }


}