<?php
/**
 * This file is part of the billag package.
 *
 * (c) net working AG <info@networking.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Networking\ElasticSearchBundle\Paginator;

use FOS\ElasticaBundle\Paginator\RawPartialResults;

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
        return array_map(function ($result) {

            $highlights = $result->getHighlights();
            $source = $result->getSource();

//            foreach($source as $key =>  $field){
//                if(array_key_exists($key, $highlights)){
//                    $source[$key] = $highlights[$key][0];
//                }
//            }


            return $source;

        }, $this->resultSet->getResults());
    }
}
