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

use Illuminate\Contracts\Cache\Repository;
use RZ\MixedFeed\FeedProvider\TwitterFeedProvider;

/**
 * Get a Twitter search tweets feed.
 */
class TwitterSearchFeed extends TwitterFeedProvider
{
    protected $queryParams;

    /**
     *
     * @param array           $queryParams
     * @param string          $consumerKey
     * @param string          $consumerSecret
     * @param string          $accessToken
     * @param string          $accessTokenSecret
     * @param Repository|null $cacheProvider
     * @param callable|null   $callback
     */
    public function __construct(
        array $queryParams,
        $consumerKey,
        $consumerSecret,
        $accessToken,
        $accessTokenSecret,
        Repository $cacheProvider = null,
        $callback = null
    ) {
        parent::__construct(
            $consumerKey,
            $consumerSecret,
            $accessToken,
            $accessTokenSecret,
            $cacheProvider,
            $callback
        );

        $this->queryParams = array_filter($queryParams);
    }

    /**
     * {@inheritdoc}
     */
    public function getFeedPlatform()
    {
        return 'search';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEndpoint()
    {
        return 'search/tweets';
    }

    /**
     * {@inheritdoc}
     */
    protected function buildCacheKey($count)
    {
        $platform = $this->getFeedPlatform();
        
        $prefix = "{$this->getFeedProvider()}" . (
            !empty($platform) ? ":{$platform}" : ""
        );
        
        $query = md5(serialize($this->queryParams));

        return "{$prefix}:{$query}:{$count}";
    }

    /**
     * {@inheritdoc}
     */
    protected function buildRequestData($count)
    {
        // query parameters
        $params = [
            'q' => $this->formatQueryParams(),
            'count' => $count,
            'include_entities' => true,
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

    /**
     * {@inheritdoc}
     */
    protected function getResponseData($body)
    {
        return $body->statuses;
    }

    /**
     * Format the query parameters according to Twitter's API.
     *
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
