<?php
/**
 * This file is part of the forel package.
 *
 * (c) net working AG <info@networking.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Networking\ElasticSearchBundle\Paginator;

use Elastica\Result;
use FOS\ElasticaBundle\Paginator\RawPartialResults;
use Enzim\Lib\TikaWrapper\TikaWrapper;

/**
 * @author Yorkie Chadwick <y.chadwick@networking.ch>
 */
class RawResults extends RawPartialResults
{
    /**
     * {@inheritDoc}
     */
    public function toArray()
    {
        /** @var Result $hit */
        return array_map(function ($result) {

            $source = $result->getSource();
            $source['highlights'] = $result->getHighlights();
            $source['score'] = $result->getScore();

            if(array_key_exists('file', $source) && !array_key_exists('file.content', $source['highlights'])){
                $source['file']['content'] = TikaWrapper::getText($source['file']['_name']);
            }
            return $source;

        }, $this->resultSet->getResults());
    }
}
