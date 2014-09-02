<?php

namespace Juneym\Cache\Storage\Adapter;

use Zend\Cache\Storage;
/**
 * TaggablInterface
 *
 * Extends the Zend\Cache\Storage\TaggableInterface
 * to have a method called getByTags(array $tags)
 */
interface TaggableInterface extends Storage\TaggableInterface
{

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
    public function getByTags(array $tags,  $disjunction = false);
}