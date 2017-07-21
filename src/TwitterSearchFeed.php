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
 * @file TwitterSearchFeed.php
 * @author Ambroise Maupate
 */
namespace RZ\MixedFeed;

use Illuminate\Cache\Repository;
use Abraham\TwitterOAuth\TwitterOAuthException;
use RZ\MixedFeed\FeedProvider\TwitterFeedProvider;

/**
 * Get a Twitter search tweets feed.
 */
class TwitterSearchFeed extends TwitterFeedProvider
{
    const TIME_KEY = 'created_at';

    protected $queryParams;

    /**
     *
     * @param array              $queryParams
     * @param string             $consumerKey
     * @param string             $consumerSecret
     * @param string             $accessToken
     * @param string             $accessTokenSecret
     * @param CacheProvider|null $cacheProvider
     */
    public function __construct(
        array $queryParams,
        $consumerKey,
        $consumerSecret,
        $accessToken,
        $accessTokenSecret,
        Repository $cacheProvider = null
    ) {
        parent::__construct(
            $consumerKey,
            $consumerSecret,
            $accessToken,
            $accessTokenSecret
        );

        $this->queryParams = array_filter($queryParams);
        $this->cacheProvider = $cacheProvider;
    }

    protected function getFeed($count = 5)
    {
        try {
            // cache key
            $cacheKey = $this->buildCacheKey($count);

            // do we have this data in the cache ?
            if ($data = $this->fetchFromCache($cacheKey)) {
                return $data;
            }

            // call the api and get response
            $body = $this->twitterConnection->get("search/tweets", [
                "q" => $this->formatQueryParams(),
                "count" => $count,
            ]);

            // did the call return with an error ?
            if ($this->twitterConnection->getLastHttpCode() !== 200) {
                return $body;
            }

            // put this data in the cache
            $this->saveToCache($cacheKey, $body->statuses);

            return $body->statuses;
        } catch (TwitterOAuthException $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFeedPlatform()
    {
        return 'twitter_search';
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
        $query = md5(serialize($this->queryParams));
        return "{$platform}:{$query}:{$count}";
    }

    /**
     * @return string
     */
    private function formatQueryParams()
    {
        $inlineParams = [];
        foreach ($this->queryParams as $key => $value) {
            if (is_numeric($key)) {
                $inlineParams[] = $value;
            } else {
                $inlineParams[] = $key . ':' . $value;
            }
        }

        return implode(' ', $inlineParams);
    }
}
