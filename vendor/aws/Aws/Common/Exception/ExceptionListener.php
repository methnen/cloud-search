<?php

/**
 * Copyright 2010-2013 Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 * http://aws.amazon.com/apache2.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */
namespace WP_Cloud_Search\Aws\Common\Exception;

use WP_Cloud_Search\Guzzle\Common\Event;
use WP_Cloud_Search\Symfony\Component\EventDispatcher\EventSubscriberInterface;
/**
 * Converts generic Guzzle response exceptions into AWS specific exceptions
 */
class ExceptionListener implements \WP_Cloud_Search\Symfony\Component\EventDispatcher\EventSubscriberInterface
{
    /**
     * @var ExceptionFactoryInterface Factory used to create new exceptions
     */
    protected $factory;
    /**
     * @param ExceptionFactoryInterface $factory Factory used to create exceptions
     */
    public function __construct(\WP_Cloud_Search\Aws\Common\Exception\ExceptionFactoryInterface $factory)
    {
        $this->factory = $factory;
    }
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array('request.error' => array('onRequestError', -1));
    }
    /**
     * Throws a more meaningful request exception if available
     *
     * @param Event $event Event emitted
     */
    public function onRequestError(\WP_Cloud_Search\Guzzle\Common\Event $event)
    {
        $e = $this->factory->fromResponse($event['request'], $event['response']);
        $event->stopPropagation();
        throw $e;
    }
}
