<?php
/**
 * Copyright © 2015, Ambroise Maupate
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @file AbstractFeedProvider.php
 * @author Ambroise Maupate
 */
namespace RZ\MixedFeed\FeedProvider;

use RZ\MixedFeed\FeedProviderInterface;
use RZ\MixedFeed\Exception\FeedProviderErrorException;

/**
 * Implements a basic feed provider with
 * platform name and \DateTime injection.
 */
abstract class AbstractFeedProvider implements FeedProviderInterface
{
    protected $ttl = 7200;
    protected $cacheProvider;
    protected $callback;

    /**
     * {@inheritdoc}
     */
    public function getItems($count = 5)
    {
        // will hold the normalized feed items
        $nList = [];

        // get feed from provider
        $list = $this->getFeed($count);

        // normalize feed
        if ($this->isValid($list)) {
            foreach ($list as $index => $item) {
                if (is_object($item)) {
                    $nItem = new \stdClass;

                    // inject dynamic attributes that will allow merging feed items of different types
                    $nItem->feed_item_provider = $this->getFeedProvider();
                    $nItem->feed_item_platform = $this->getFeedPlatform();
                    $nItem->canonical_id = $this->getCanonicalId($item);
                    $nItem->canonical_app = $this->getCanonicalApp($item);
                    $nItem->canonical_message = $this->getCanonicalMessage($item);
                    $nItem->normalized_date = $this->getDateTime($item);
                    $nItem->original_data = $item;
                    
                    // append normalized item to list
                    $nList[] = $nItem;
                }
            }

            // fire the callback if one has been informed
            if ($this->callback) {
                ($this->callback)($nList);
            }

            // return normalized list
            return $nList;
        } else {
            throw new FeedProviderErrorException($this->getFeedPlatform(), $this->getErrors($list));
        }
    }

    /**
     * Try to fetch data from the cache.
     *
     * @return mixed
     */
    public function fetchFromCache($cacheKey)
    {
        // do we have this data in the cache ?
        if (null !== $this->cacheProvider &&
            $this->cacheProvider->has($cacheKey)) {
            return $this->cacheProvider->get($cacheKey);
        }
        return null;
    }

    /**
     * Save data to the cache.
     */
    public function saveToCache($cacheKey, $data)
    {
        // should we put this data in the cache ?
        if (null !== $this->cacheProvider) {
            $this->cacheProvider->put(
                $cacheKey,
                $data,
                $this->ttl
            );
        }
    }

    /**
     * Gets the value of ttl.
     *
     * @return integer
     */
    public function getTtl()
    {
        return $this->ttl;
    }

    /**
     * Sets the value of ttl.
     *
     * @param integer $ttl the ttl
     *
     * @return self
     */
    public function setTtl($ttl)
    {
        $this->ttl = $ttl;

        return $this;
    }
}
