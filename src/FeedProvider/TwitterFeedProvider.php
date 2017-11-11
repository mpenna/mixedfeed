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

use Abraham\TwitterOAuth\TwitterOAuth;
use Illuminate\Contracts\Cache\Repository;
use Abraham\TwitterOAuth\TwitterOAuthException;
use RZ\MixedFeed\Exception\CredentialsException;
use RZ\MixedFeed\FeedProvider\AbstractFeedProvider;

/**
 * Implements a basic Twitter feed provider.
 */
abstract class TwitterFeedProvider extends AbstractFeedProvider
{
    const TIME_KEY = 'created_at';

    protected $sinceId = null;
    protected $maxId = null;
    protected $twitterConnection;

    /**
     *
     * @param string          $consumerKey
     * @param string          $consumerSecret
     * @param string          $accessToken
     * @param string          $accessTokenSecret
     * @param Repository|null $cacheProvider
     * @param callable|null   $callback
     */
    public function __construct(
        $consumerKey,
        $consumerSecret,
        $accessToken,
        $accessTokenSecret,
        Repository $cacheProvider = null,
        $callback = null
    ) {
        if (null === $consumerKey ||
            false === $consumerKey ||
            empty($consumerKey)) {
            throw new CredentialsException("TwitterFeed needs a valid consumer key.", 1);
        }

        if (null === $consumerSecret ||
            false === $consumerSecret ||
            empty($consumerSecret)) {
            throw new CredentialsException("TwitterFeed needs a valid consumer secret.", 1);
        }

        // if (null === $accessToken ||
        //     false === $accessToken ||
        //     empty($accessToken)) {
        //     throw new CredentialsException("TwitterFeed needs a valid access token.", 1);
        // }

        // if (null === $accessTokenSecret ||
        //     false === $accessTokenSecret ||
        //     empty($accessTokenSecret)) {
        //     throw new CredentialsException("TwitterFeed needs a valid access token secret.", 1);
        // }

        $this->twitterConnection = new TwitterOAuth(
            $consumerKey,
            $consumerSecret,
            $accessToken,
            $accessTokenSecret
        );

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

            // the endpoint
            $endpoint = $this->getEndpoint();

            // query parameters
            $params = $this->buildRequestData($count);

            // call the api and get response
            $body = $this->twitterConnection->get($endpoint, $params);

            // did the call return with an error ?
            if ($this->twitterConnection->getLastHttpCode() !== 200) {
                return $body;
            }

            // extract response data
            $data = $this->getResponseData($body);

            // put this data in the cache
            $this->saveToCache($cacheKey, $data);

            // return data
            return $data;
        } catch (TwitterOAuthException $e) {
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
        return 'twitter';
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
        return null !== $feed && is_array($feed);
    }

    /**
     * Gets the value of sinceId.
     *
     * @return integer
     */
    public function getSinceId()
    {
        return $this->sinceId;
    }

    /**
     * Sets the value of sinceId.
     *
     * @param integer $sinceId the since_id
     *
     * @return self
     */
    public function setSinceId($sinceId)
    {
        $this->sinceId = $sinceId;

        return $this;
    }

    /**
     * Gets the value of maxId.
     *
     * @return integer
     */
    public function getMaxId()
    {
        return $this->maxId;
    }

    /**
     * Sets the value of maxId.
     *
     * @param integer $maxId the max_id
     *
     * @return self
     */
    public function setMaxId($maxId)
    {
        $this->maxId = $maxId;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrors($feed)
    {
        $errors = "";

        if (null !== $feed && null !== $feed->errors && !empty($feed->errors)) {
            foreach ($feed->errors as $error) {
                $errors .= "[" . $error->code . "] ";
                $errors .= $error->message . PHP_EOL;
            }
        }

        return $errors;
    }

    /**
     * {@inheritdoc}
     */
    public function getCanonicalMessage($item)
    {
        return $item->text;
    }

    public function getCanonicalMedia($item)
    {
        $medias = new \stdClass;
        $photos = [];
        $videos = [];

        // photos

        if (isset($item->entities->media)
            && is_array($item->entities->media)) {

            foreach($item->entities->media as $media) {

                if (isset($media->type)
                    && $media->type == 'photo') {

                    if (isset($media->media_url)
                        && isset($media->sizes)) {

                        $sizes = array_keys((array)$media->sizes);

                        foreach ($sizes as $size) {
                            $photos[] = [
                                'name' => $size,
                                'url' => "{$media->media_url}:{$size}",
                                'size' => [
                                    isset($media->sizes->{$size}->h) ? $media->sizes->{$size}->h : 0,
                                    isset($media->sizes->{$size}->w) ? $media->sizes->{$size}->w : 0,
                                ],
                            ];
                        }

                    }

                }

            }

        }

        // videos

        if (isset($item->extended_entities->media)
            && is_array($item->extended_entities->media)) {

            foreach ($item->extended_entities->media as $media) {

                if (isset($media->type)
                    && $media->type == 'video') {

                    if (isset($media->video_info->variants)
                        && is_array($media->video_info->variants)) {

                        foreach ($media->video_info->variants as $variant) {
                            $videos[] = [
                                'url' => isset($variant->url) ? $variant->url : '',
                            ];
                        }

                    }

                }

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
        return isset($item->id_str) ? $item->id_str : '';
    }

    /**
     * {@inheritdoc}
     */
    public function getCanonicalApp($item)
    {
        return isset($item->source) ? strip_tags($item->source) : '';
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
