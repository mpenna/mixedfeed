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
 * @file FacebookUserFeed.php
 * @author Ambroise Maupate
 */
namespace RZ\MixedFeed;

use GuzzleHttp\Exception\ClientException;
use Illuminate\Contracts\Cache\Repository;
use RZ\MixedFeed\Exception\CredentialsException;
use RZ\MixedFeed\FeedProvider\AbstractFeedProvider;

/**
 * Get a Facebook user timeline feed using an Access Token.
 *
 * https://developers.facebook.com/docs/facebook-login/access-tokens
 */
class FacebookUserFeed extends AbstractFeedProvider
{
    const TIME_KEY = 'created_time';
    
    protected $userId;
    protected $accessToken;
    protected $fields;
    protected $since = null;
    protected $until = null;

    /**
     *
     * @param string             $userId
     * @param string             $accessToken Your App Token
     * @param CacheProvider|null $cacheProvider
     * @param array              $fields
     */
    public function __construct(
        $userId,
        $accessToken,
        Repository $cacheProvider = null,
        $fields = [],
        $callback = null
    ) {
        $this->userId = $userId;
        $this->accessToken = $accessToken;
        $this->cacheProvider = $cacheProvider;

        $this->fields = ['link', 'picture', 'full_picture', 'message', 'story', 'type', 'created_time', 'source', 'status_type'];
        $this->fields = array_unique(array_merge($this->fields, $fields));

        $this->callback = $callback;

        if (null === $this->accessToken ||
            false === $this->accessToken ||
            empty($this->accessToken)) {
            throw new CredentialsException("FacebookUserFeed needs a valid user access token.", 1);
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
                    'fields' => implode(',', $this->fields),
                ],
            ];

            // filter by date range: since
            if (null !== $this->since &&
                $this->since instanceof \Datetime) {
                $params['query']['since'] = $this->since->getTimestamp();
            }
            
            // filter by date range: until
            if (null !== $this->until &&
                $this->until instanceof \Datetime) {
                $params['query']['until'] = $this->until->getTimestamp();
            }

            // call the api and get response
            $response = $client->get('https://graph.facebook.com/' . $this->userId . '/posts', $params);

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
    public function getFeedProvider()
    {
        return 'facebook';
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
    public function getDateTime($item)
    {
        $date = new \DateTime();
        $date->setTimestamp(strtotime($item->{self::TIME_KEY}));
        return $date;
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
        return isset($item->message) ? $item->message : '';
    }

    /**
     * {@inheritdoc}
     */
    public function getCanonicalId($item)
    {
        return isset($item->id) ? $item->id : '';
    }

    /**
     * {@inheritdoc}
     */
    public function isValid($feed)
    {
        return null !== $feed && is_array($feed) && !isset($feed['error']);
    }

    /**
     * Gets the value of since.
     *
     * @return \Datetime
     */
    public function getSince()
    {
        return $this->since;
    }

    /**
     * Sets the value of since.
     *
     * @param \Datetime $since the since
     *
     * @return self
     */
    public function setSince(\Datetime $since)
    {
        $this->since = $since;

        return $this;
    }

    /**
     * Gets the value of until.
     *
     * @return \Datetime
     */
    public function getUntil()
    {
        return $this->until;
    }

    /**
     * Sets the value of until.
     *
     * @param \Datetime $until the until
     *
     * @return self
     */
    public function setUntil(\Datetime $until)
    {
        $this->until = $until;

        return $this;
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
        $provider = $this->getFeedProvider();
        $platform = $this->getFeedPlatform();
        $user = $this->userId;

        return "{$provider}" . !empty($platform) 
            ? ":{$platform}:{$user}:{$count}"
            : ":{$user}:{$count}";
    }
}
