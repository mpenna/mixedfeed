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

use Illuminate\Cache\Repository;
use Abraham\TwitterOAuth\TwitterOAuth;
use RZ\MixedFeed\Exception\CredentialsException;
use RZ\MixedFeed\FeedProvider\AbstractFeedProvider;

/**
 * Get a Twitter tweets abstract feed.
 */
class TwitterFeedProvider extends AbstractFeedProvider
{
    const TIME_KEY = 'created_at';

    protected $twitterConnection;

    /**
     *
     * @param string             $consumerKey
     * @param string             $consumerSecret
     * @param string             $accessToken
     * @param string             $accessTokenSecret
     */
    public function __construct(
        $consumerKey,
        $consumerSecret,
        $accessToken, 
        $accessTokenSecret
    ) {
        if (null === $consumerKey ||
            false === $consumerKey ||
            empty($consumerKey)) {
            throw new CredentialsException("TwitterSearchFeed needs a valid consumer key.", 1);
        }

        if (null === $consumerSecret ||
            false === $consumerSecret ||
            empty($consumerSecret)) {
            throw new CredentialsException("TwitterSearchFeed needs a valid consumer secret.", 1);
        }

        if (null === $accessToken ||
            false === $accessToken ||
            empty($accessToken)) {
            throw new CredentialsException("TwitterSearchFeed needs a valid access token.", 1);
        }

        if (null === $accessTokenSecret ||
            false === $accessTokenSecret ||
            empty($accessTokenSecret)) {
            throw new CredentialsException("TwitterSearchFeed needs a valid access token secret.", 1);
        }

        $this->twitterConnection = new TwitterOAuth(
            $consumerKey,
            $consumerSecret,
            $accessToken,
            $accessTokenSecret
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFeedPlatform()
    {
        return 'twitter';
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
}
