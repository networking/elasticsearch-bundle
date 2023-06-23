<?php

declare(strict_types=1);

/**
 * This file is part of the forel-2016  package.
 *
 * (c) net working AG <info@networking.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Networking\ElasticSearchBundle\Elastica;

use Elastica\Exception\ExceptionInterface;
use Elastica\Request;
use Elastica\Response;
use FOS\ElasticaBundle\Elastica\Client as BaseClient;

class Client extends BaseClient
{
    public function request($path, $method = Request::GET, $data = [], array $query = [], string $contentType = \Elastica\Request::DEFAULT_CONTENT_TYPE)
    {
        try {
            return parent::request($path, $method, $data, $query);
        } catch (ExceptionInterface $e) {
            if ($this->_logger) {
                $this->_logger->warning('Failed to send a request to ElasticSearch', [
                    'exception' => $e->getMessage(),
                    'path' => $path,
                    'method' => $method,
                    'data' => $data,
                    'query' => $query,
                ]);
            }

            return new Response('{"took":0,"timed_out":false,"hits":{"total":0,"max_score":0,"hits":[]}}');
        }
    }
}
