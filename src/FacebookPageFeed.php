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
 * @file FacebookPageFeed.php
 * @author Ambroise Maupate
 */
namespace RZ\MixedFeed;

use Illuminate\Contracts\Cache\Repository;
use RZ\MixedFeed\FeedProvider\FacebookFeedProvider;

/**
 * Get a Facebook public page timeline feed using an App Token.
 *
 * https://developers.facebook.com/docs/facebook-login/access-tokens
 */
class FacebookPageFeed extends FacebookFeedProvider
{
    protected $pageId;

    /**
     *
     * @param string          $pageId
     * @param string          $accessToken App Access Token
     * @param Repository|null $cacheProvider
     * @param array           $fields
     * @param callable|null   $callback
     */
    public function __construct(
        $pageId,
        $accessToken,
        Repository $cacheProvider = null,
        array $fields = [],
        $callback = null
    ) {
        parent::__construct(
            $accessToken,
            $cacheProvider,
            $fields,
            $callback
        );

        $this->pageId = $pageId;
    }

    /**
     * {@inheritdoc}
     */
    public function getFeedPlatform()
    {
        return 'page';
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
        return 'https://graph.facebook.com/' . $this->pageId . '/posts';
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

        $page = $this->pageId;

        return "{$prefix}:{$page}:{$count}";
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
                'limit' => $count,
                'fields' => implode(',', $this->fields),
            ],
        ];

        // filter by date range: since
        if ($this->since !== null &&
            $this->since instanceof \Datetime) {
            $params['query']['since'] = $this->since->getTimestamp();
        }

        // filter by date range: until
        if ($this->until !== null &&
            $this->until instanceof \Datetime) {
            $params['query']['until'] = $this->until->getTimestamp();
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

    /**
     * {@inheritdoc}
     */
    public function getCanonicalMessage($item)
    {
        return isset($item->message)
            ? $item->message
            : (isset($item->story)
                ? $item->story
                : '');
    }

    /**
     * {@inheritdoc}
     */
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

        } else if (isset($item->full_picture)
                    && is_string($item->full_picture)) {

            $photos[] = [
                'url' => $item->full_picture,
                'size' => [
                    //
                ]
            ];

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
    public function getCanonicalApp($item)
    {
        return isset($item->from->id) ? $item->from->id : '';
    }
}
