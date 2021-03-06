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
 * @file MixedFeed.php
 * @author Ambroise Maupate
 */
namespace RZ\MixedFeed;

use Illuminate\Cache\Repository;
use RZ\MixedFeed\MockObject\ErroredFeedItem;
use RZ\MixedFeed\Contracts\FeedProviderInterface;
use RZ\MixedFeed\FeedProvider\AbstractFeedProvider;
use RZ\MixedFeed\Exception\FeedProviderErrorException;

/**
 * Combine feed providers and sort them anti-chronologically.
 */
class MixedFeed extends AbstractFeedProvider
{
    protected $userId;
    protected $providers;

    /**
     * Create a mixed feed composed of hetergeneous feed
     * providers.
     *
     * @param string          $userId
     * @param array           $providers
     * @param Repository|null $cacheProvider
     */
    public function __construct(
        $userId,
        array $providers = [],
        Repository $cacheProvider = null
    ) {
        foreach ($providers as $provider) {
            if (!($provider instanceof FeedProviderInterface)) {
                throw new \RuntimeException("Provider must implement FeedProviderInterface interface.", 1);
            }
        }

        $this->userId = $userId;
        $this->providers = $providers;
        $this->cacheProvider = $cacheProvider;
    }

    /**
     * Get the social provider name.
     *
     * @return string
     */
    public function getFeedProvider()
    {
        return 'mixed';
    }

    /**
     * {@inheritdoc}
     */
    public function getFeedPlatform()
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getItems($count = 5)
    {
        $list = [];
        
        if (count($this->providers) > 0) {

            // cache key
            $cacheKey = $this->buildCacheKey($count);

            // do we have this data in the cache ?
            if ($data = $this->fetchFromCache($cacheKey)) {
                return $data;
            }

            // $perProviderCount = floor($count / count($this->providers));
            $perProviderCount = $count;

            // merge feeds
            foreach ($this->providers as $provider) {
                try {
                    $list = array_merge($list, $provider->getItems($perProviderCount));
                } catch (FeedProviderErrorException $e) {
                    $list = array_merge($list, [
                        new ErroredFeedItem($e->getMessage(), $provider->getFeedPlatform()),
                    ]);
                }
            }

            // sort feeds
            usort($list, function (\stdClass $a, \stdClass $b) {
                $aDT = $a->normalized_date;
                $bDT = $b->normalized_date;

                if ($aDT == $bDT) {
                    return 0;
                }

                // DESC sorting
                // return ($aDT > $bDT) ? -1 : 1;
                // ASC sorting
                return ($aDT > $bDT) ? 1 : -1;
            });

            // put this data in the cache
            $this->saveToCache($cacheKey, $list);
        }

        return $list;
    }

    /**
     * {@inheritdoc}
     */
    public function getDateTime($item)
    {
        return new \DateTime('now');
    }

    /**
     * {@inheritdoc}
     */
    public function getErrors($feed)
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getCanonicalMessage($item)
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getCanonicalMedia($item)
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getCanonicalId($item)
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getCanonicalApp($item)
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function filterOutItem($item)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isValid($feed)
    {
        return true;
    }

    /**
     * Builds the cache key for this feed.
     *
     * @param integer $count number of items to fetch
     *
     * @return string
     */
    private function buildCacheKey($count)
    {
        $platform = $this->getFeedPlatform();
        
        $prefix = "{$this->getFeedProvider()}" . (
            !empty($platform) ? ":{$platform}" : ""
        );

        $user = $this->userId;

        return "{$prefix}:{$user}:{$count}";
    }
}
