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
 * @file InstagramFeed.php
 * @author Ambroise Maupate
 */
namespace RZ\MixedFeed;

use Illuminate\Cache\Repository;
use RZ\MixedFeed\FeedProvider\InstagramFeedProvider;

/**
 * Get an Instagram user feed.
 */
class InstagramUserFeed extends InstagramFeedProvider
{
    protected $userId;

    /**
     *
     * @param string          $userId
     * @param string          $accessToken Access Token
     * @param Repository|null $cacheProvider
     * @param callable|null   $callback
     */
    public function __construct(
        $userId,
        $accessToken,
        Repository $cacheProvider = null,
        $callback = null
    ) {
        parent::__construct(
            $accessToken,
            $cacheProvider,
            $callback
        );

        $this->userId = $userId;
    }

    /**
     * {@inheritdoc}
     */
    public function getFeedPlatform()
    {
        return 'user';
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
    protected function getEndpoint()
    {
        return 'https://api.instagram.com/v1/users/' . $this->userId . '/media/recent';
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

        $user = $this->userId;

        return "{$prefix}:{$user}:{$count}";
    }

    /**
     * {@inheritdoc}
     */
    protected function buildRequestData($count)
    {
        // query parameters
        $params = [
            'query' => [
                'access_token' => $this->accessToken,
                'count' => $count,
            ],
        ];

        // filter by id range: minId
        if ($this->minId !== null) {
            $params['query']['min_id'] = $this->minId;
        }

        // filter by id range: maxId
        if ($this->maxId !== null) {
            $params['query']['max_id'] = $this->maxId;
        }

        return $params;
    }

    /**
     * {@inheritdoc}
     */
    protected function getResponseData($body)
    {
        return $body->data;
    }
}
