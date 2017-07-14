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
    const timeKey = 'created_at';

    protected $userId;
    protected $accessToken;
    protected $cacheKey;
    protected $twitterConnection;
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
     */
    public function __construct(
        $userId,
        $consumerKey,
        $consumerSecret,
        $accessToken,
        $accessTokenSecret,
        Repository $cacheProvider = null,
        $excludeReplies = true,
        $includeRts = false
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
    }

    protected function getFeed($count = 5)
    {
        try {
            // cache key
            $cacheKey = $this->buildCacheKey($this->userId, $count);

            // do we have this data in the cache ?
            if ($data = $this->fetchFromCache($cacheKey)) {
                return $data;
            }
            
            // call the api and get response
            $body = $this->twitterConnection->get("statuses/user_timeline", [
                "user_id" => $this->userId,
                "count" => $count,
                "exclude_replies" => $this->excludeReplies,
                'include_rts' => $this->includeRts,
            ]);

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
        return 'twitter_status';
    }

    /**
     * Builds the cache key for this feed.
     *
     * @param integer $count number of items to fetch
     *
     * @return string
     */
    private function buildCacheKey($user, $count)
    {
        $platform = $this->getFeedPlatform();
        return "{$platform}:{$user}:{$count}";
    }
}
