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

    /**
     * {@inheritdoc}
     */
    public function getItems($count = 5)
    {
        $list = $this->getFeed($count);

        if ($this->isValid($list)) {
            // inject feedItemPlatform, normalizedDate and canonicalMessage
            // to be able to merge them with other types
            foreach ($list as $index => $item) {
                if (is_object($item)) {
                    \Log::info('AbstractFeedProvider->getItems() isValid and item is object', [$item]);
                    $item->feedItemPlatform = $this->getFeedPlatform();
                    $item->normalizedDate = $this->getDateTime($item);
                    $item->canonicalMessage = $this->getCanonicalMessage($item);
                } else {
                    \Log::info('AbstractFeedProvider->getItems() isValid but item is NOT object', [$item]);
                    unset($list[$index]);
                }
            }
            return $list;
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
