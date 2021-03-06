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
 * @file PinterestBoardFeed.php
 * @author Ambroise Maupate
 */
namespace RZ\MixedFeed;

use Illuminate\Cache\Repository;
use GuzzleHttp\Exception\ClientException;
use RZ\MixedFeed\Exception\CredentialsException;
use RZ\MixedFeed\FeedProvider\AbstractFeedProvider;

/**
 * Get a Pinterest public board pins feed.
 *
 * https://developers.pinterest.com/tools/access_token/
 */
class PinterestBoardFeed extends AbstractFeedProvider
{
    const TIME_KEY = 'created_at';

    protected $boardId;
    protected $accessToken;

    /**
     *
     * @param string             $boardId
     * @param string             $accessToken
     * @param CacheProvider|null $cacheProvider
     */
    public function __construct(
        $boardId,
        $accessToken,
        Repository $cacheProvider = null
    ) {
        $this->boardId = $boardId;
        $this->accessToken = $accessToken;
        $this->cacheProvider = $cacheProvider;

        if (null === $this->accessToken ||
            false === $this->accessToken ||
            empty($this->accessToken)) {
            throw new CredentialsException("PinterestBoardFeed needs a valid access token.", 1);
        }
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
            
            // http client
            $client = new \GuzzleHttp\Client();

            // query parameters
            $params = [
                'query' => [
                    'access_token' => $this->accessToken,
                    'limit' => $count,
                    'fields' => 'id,color,created_at,creator,media,image[original],note,link,url',
                ],
            ];
            
            // call the api and get response
            $response = $client->get('https://api.pinterest.com/v1/boards/' . $this->boardId . '/pins/', $params);

            // decode body
            $body = json_decode($response->getBody());

            // put this data in the cache
            $this->saveToCache($cacheKey, $body->data);

            return $body->data;
        } catch (ClientException $e) {
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
        return 'pinterest_board';
    }

    /**
     * {@inheritdoc}
     */
    public function getDateTime($item)
    {
        $date = new \DateTime();
        $date->setTimestamp(strtotime($item->{self::TIME_KEY}));
        return $date;
    }

    /**
     * {@inheritdoc}
     */
    public function isValid($feed)
    {
        return null !== $feed && is_array($feed) && !isset($feed['error']);
    }

    /**
     * {@inheritdoc}
     */
    public function getErrors($feed)
    {
        return $feed['error'];
    }

    /**
     * {@inheritdoc}
     */
    public function getCanonicalMessage($item)
    {
        return $item->note;
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
        $board = $this->boardId;
        return "{$platform}:{$board}:{$count}";
    }
}
