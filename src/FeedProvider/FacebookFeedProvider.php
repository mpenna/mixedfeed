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

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Contracts\Cache\Repository;
use RZ\MixedFeed\Exception\CredentialsException;
use RZ\MixedFeed\FeedProvider\AbstractFeedProvider;

/**
 * Implements a basic Facebook feed provider.
 */
abstract class FacebookFeedProvider extends AbstractFeedProvider
{
    const TIME_KEY = 'created_time';

    protected $accessToken;
    protected $fields;
    protected $since = null;
    protected $until = null;

    /**
     *
     * @param string          $accessToken Access Token
     * @param Repository|null $cacheProvider
     * @param array           $fields
     * @param callable|null   $callback
     */
    public function __construct(
        $accessToken,
        Repository $cacheProvider = null,
        array $fields = [],
        $callback = null
    ) {
        if (null === $accessToken ||
            false === $accessToken ||
            empty($accessToken)) {
            throw new CredentialsException("FacebookFeed needs a valid user access token.", 1);
        }

        $this->accessToken = $accessToken;
        $this->cacheProvider = $cacheProvider;

        // $this->fields = ['application', 'link', 'picture', 'full_picture', 'message', 'story', 'type', 'created_time', 'source', 'status_type'];
        $this->fields = ['application', 'created_time', 'full_picture', 'link', 'message', 'picture', 'source', 'status_type', 'story', 'type'];
        $this->fields = array_unique(array_merge($this->fields, $fields));

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
            // $client = new \GuzzleHttp\Client();
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
        return '';
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
    // public function getCanonicalMedia($item)
    // {
    //     $medias = [];

    //     if (isset($item->attachments->data) 
    //         && is_array($item->attachments->data)) {

    //         $imageItems = array_filter($item->attachments->data, function($obj) {
    //             return isset($obj->media->image);
    //         });

    //         foreach ($imageItems as $imageItem) {
    //             // \Log::info('getCanonicalMedia', [$imageItem]);
    //             $medias[] = [
    //                 'id' => '',
    //                 'variants' => [
    //                     'type' => 'image',
    //                     // 'name' => '',
    //                     'url' => $imageItem->media->image->src,
    //                     'size' => [
    //                         $imageItem->media->image->height,
    //                         $imageItem->media->image->width,
    //                     ]
    //                 ]
    //             ];
    //         }

    //     }

    //     $videos = [];

    //     if (isset($item->source)) {

    //         $videos[] = [
    //             'type' => 'video',
    //             // 'name' => '',
    //             'url' => $item->source,
    //             // 'size' => [],
    //             'bitrate' => null,
    //             // 'content_type' => isset($variant->content_type) ? $variant->content_type : '',
    //         ];

    //         foreach ($videos as $video) {
    //             // $medias[$key]['variants'][] = $video;
    //             // $medias['videos'][] = $video;
    //             $medias['videos'][] = $video;
    //         }
    //     }

    //     return $medias;
    // }

    public function getCanonicalMedia($item)
    {
        $medias = new \stdClass;        
        $photos = [];
        $videos = [];

        // photos

        if (isset($item->attachments->data) 
            && is_array($item->attachments->data)) {

            $images = array_filter($item->attachments->data, function($obj) {
                return isset($obj->media->image);
            });

            foreach ($images as $image) {
                $photos[] = [
                    'url' => $image->media->image->src,
                    'size' => [
                        $image->media->image->height,
                        $image->media->image->width,
                    ]
                ];
            }

        }

        // videos

        if (isset($item->source)) {
            $videos[] = [
                'url' => $item->source,
            ];
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