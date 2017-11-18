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
 * @file DatabasePostsFeed.php
 * @author Ambroise Maupate
 */
namespace RZ\MixedFeed;

// use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Repository;
use GuzzleHttp\Exception\ClientException;
use RZ\MixedFeed\Exception\CredentialsException;
use RZ\MixedFeed\FeedProvider\AbstractFeedProvider;

/**
 * Get a Facebook user timeline feed using an Access Token.
 *
 * https://developers.facebook.com/docs/facebook-login/access-tokens
 */
class DatabasePostsFeed extends AbstractFeedProvider
{
    const TIME_KEY = 'created_at';

    protected $userId;
    protected $postIds;
    protected $db;
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
        // $userId,
        $postIds,
        // $db,
        // $accessToken,
        Repository $cacheProvider = null
        // $fields = []
    ) {
        // $this->userId = $userId;
        $this->postIds = $postIds;
        // $this->db = $db;
        // $this->accessToken = $accessToken;
        $this->cacheProvider = $cacheProvider;

        $this->db = \DB::connection('mysql_api');

        // $this->fields = ['link', 'picture', 'full_picture', 'message', 'story', 'type', 'created_time', 'source', 'status_type'];
        // $this->fields = array_unique(array_merge($this->fields, $fields));

        // if (null === $this->accessToken ||
        //     false === $this->accessToken ||
        //     empty($this->accessToken)) {
        //     throw new CredentialsException("DatabasePostsFeed needs a valid user access token.", 1);
        // }

        // if (null === $this->userId ||
        //     false === $this->userId ||
        //     empty($this->userId)) {
        //     throw new CredentialsException("DatabasePostsFeed needs a valid user id.", 1);
        // }

        // if (null === $this->db ||
        //     false === $this->db ||
        //     empty($this->db)) {
        //     throw new CredentialsException("DatabasePostsFeed needs a valid database connection.", 1);
        // }
    }

    protected function getFeed($count = 5)
    {
        try {

            // get post keys from user timeline
            // $postIds = $this->db->table('timelines')
            //     ->select('post_id')
            //     ->where('user_id', '=', $this->userId)
            //     ->orderBy('created_at', 'desc')
            //     ->pluck('post_id')
            //     ->all();

            $list = [];

            // foreach ($postIds as $postId) {
            foreach ($this->postIds as $postId) {

                // cache key for the post
                // $cacheKey = "post:{$postId}";
                $cacheKey = $this->buildCacheKey($postId);

                // do we have this data in the cache ?
                if ($data = $this->fetchFromCache($cacheKey)) {
                    $list[] = $data;
                    continue;
                }

                // get data from the provider
                $post = $this->db->table('posts')
                    ->select('uuid', 'where', 'what', 'url', 'data', 'created_at')
                    ->where('id', '=', $postId)
                    ->first();

                // put data in the cache
                $this->saveToCache($cacheKey, $post);

                $list[] = $post;
            }

            return $list;
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
        return 'database_post';
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
        $text = '';
        if (isset($item->what)) {
            $what = json_decode($item->what);
            if (isset($what->text)) {
                $text = $what->text;
            }
        }
        return $text;
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
    private function buildCacheKey($postId)
    {
        $platform = $this->getFeedPlatform();
        return "{$platform}:{$postId}";
    }
}
