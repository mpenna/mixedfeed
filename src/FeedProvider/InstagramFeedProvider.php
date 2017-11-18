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
 * @file TwitterFeedProvider.php
 * @author Ambroise Maupate
 */
namespace RZ\MixedFeed\FeedProvider;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Contracts\Cache\Repository;
use RZ\MixedFeed\Exception\CredentialsException;
use RZ\MixedFeed\FeedProvider\AbstractFeedProvider;

/**
 * Implements a basic Instagram feed provider.
 */
abstract class InstagramFeedProvider extends AbstractFeedProvider
{
    const TIME_KEY = 'created_time';

    protected $accessToken;
    protected $minId = null;
    protected $maxId = null;

    /**
     *
     * @param string          $accessToken Access Token
     * @param Repository|null $cacheProvider
     * @param callable|null   $callback
     */
    public function __construct(
        $accessToken,
        Repository $cacheProvider = null,
        $callback = null
    ) {
        if (null === $accessToken ||
            false === $accessToken ||
            empty($accessToken)) {
            throw new CredentialsException("InstagramFeed needs a valid user access token.", 1);
        }

        $this->accessToken = $accessToken;
        $this->cacheProvider = $cacheProvider;
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

            // http client
            $client = new GuzzleClient();

            // the endpoint
            $endpoint = $this->getEndpoint();

            // query parameters
            $params = $this->buildRequestData($count);

            // call the api and get response
            $response = $client->get($endpoint, $params);

            // decode body
            $body = json_decode($response->getBody());

            // did the call return with an error ?
            if ($response->getStatusCode() !== 200) {
                return $body;
            }

            // extract data from response
            $data = $this->getResponseData($body);

            // put this data in the cache
            $this->saveToCache($cacheKey, $data);

            return $data;
        } catch (Exception $e) {
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
        return 'instagram';
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
    public function getDateTime($item)
    {
        $date = new \DateTime();
        $date->setTimestamp($item->{self::TIME_KEY});
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
     * Gets the value of minId.
     *
     * @return string
     */
    public function getMinId()
    {
        return $this->minId;
    }

    /**
     * Sets the value of minId.
     *
     * @param string $minId
     *
     * @return self
     */
    public function setMinId(string $minId)
    {
        $this->minId = $minId;

        return $this;
    }

    /**
     * Gets the value of maxId.
     *
     * @return string
     */
    public function getMaxId()
    {
        return $this->maxId;
    }

    /**
     * Sets the value of maxId.
     *
     * @param string $maxId
     *
     * @return self
     */
    public function setmaxId(string $maxId)
    {
        $this->maxId = $maxId;

        return $this;
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
        if (null !== $item->caption) {
            return $item->caption->text;
        }

        return '';
    }

    public function getCanonicalMedia($item)
    {
        $medias = new \stdClass;
        $photos = [];
        $videos = [];

        // photos

        if (isset($item->images)) {
            foreach ($item->images as $key => $image) {
                $photos[] = [
                    'url' => $image->url,
                    'size' => [
                        $image->height,
                        $image->width,
                    ]
                ];
            }
        }

        // videos

        if (isset($item->videos)) {
            foreach ($item->videos as $key => $video) {
                $videos[] = [
                    'url' => $video->url,
                    'size' => [
                        $video->height,
                        $video->width,
                    ]
                ];
            }
        }

        $medias->images = $photos;
        $medias->videos = $videos;

        return $medias;
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
    public function getCanonicalApp($item)
    {
        return isset($item->application->id) ? $item->application->id : '';
    }

    /**
     * Gets the endpoint that should be called.
     *
     * @return string
     */
    abstract protected function getEndpoint();

    /**
     * Builds the cache key for this feed.
     *
     * @param integer $count number of items to fetch
     *
     * @return string
     */
    abstract protected function buildCacheKey($count);

    /**
     * Builds the request data.
     *
     * @param integer $count number of items to fetch
     *
     * @return mixed
     */
    abstract protected function buildRequestData($count);

    /**
     * Gets the endpoint that should be called.
     *
     * @param  mixed $body
     *
     * @return mixed
     */
    abstract protected function getResponseData($body);

}
