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
 * @file TwitterStatusFeed.php
 * @author Ambroise Maupate
 */
namespace RZ\MixedFeed;

use Illuminate\Cache\Repository;
use Abraham\TwitterOAuth\TwitterOAuthException;
use RZ\MixedFeed\FeedProvider\TwitterFeedProvider;

/**
 * Get a Twitter user timeline feed.
 */
class TwitterStatusFeed extends TwitterFeedProvider
{
    const TIME_KEY = 'created_at';

    protected $userId;
    protected $excludeReplies;
    protected $includeRts;

    /**
     *
     * @param string             $userId
     * @param string             $consumerKey
     * @param string             $consumerSecret
     * @param string             $accessToken
     * @param string             $accessTokenSecret
     * @param CacheProvider|null $cacheProvider
     * @param boolean            $excludeReplies
     * @param boolean            $includeRts
     * @param callable           $callback
     */
    public function __construct(
        $userId,
        $consumerKey,
        $consumerSecret,
        $accessToken,
        $accessTokenSecret,
        Repository $cacheProvider = null,
        $excludeReplies = true,
        $includeRts = false,
        $callback = null
    ) {
        parent::__construct(
            $consumerKey,
            $consumerSecret,
            $accessToken,
            $accessTokenSecret
        );

        $this->userId = $userId;
        $this->cacheProvider = $cacheProvider;
        $this->excludeReplies = $excludeReplies;
        $this->includeRts = $includeRts;
        $this->callback = $callback;
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
            
            // the endpoint
            $endpoint = 'statuses/user_timeline';

            // query parameters
            $params = $this->buildRequestData($count);

            // call the api and get response
            $body = $this->twitterConnection->get($endpoint, $params);

            // did the call return with an error ?
            if ($this->twitterConnection->getLastHttpCode() !== 200) {
                return $body;
            }

            // put this data in the cache
            $this->saveToCache($cacheKey, $body);

            return $body;
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
        return 'status';
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

    /**
     * Builds the request data.
     *
     * @param integer $count number of items to fetch
     *
     * @return mixed
     */
    private function buildRequestData($count)
    {
        // query parameters
        $params = [
            'user_id' => $this->userId,
            'count' => $count,
            'exclude_replies' => $this->excludeReplies,
            'include_rts' => $this->includeRts,
        ];

        // filter by id range: since_id
        if ($this->sinceId !== null &&
            is_numeric($this->sinceId)) {
            $params['since_id'] = $this->sinceId;
        }
        
        // filter by id range: max_id
        if ($this->maxId !== null &&
            is_numeric($this->maxId)) {
            $params['max_id'] = $this->maxId;
        }

        return $params;
    }
}
