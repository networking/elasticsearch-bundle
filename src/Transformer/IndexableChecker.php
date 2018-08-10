<?php
/**
 * Created by PhpStorm.
 * User: yorkie
 * Date: 19.02.18
 * Time: 09:36.
 */

namespace Networking\ElasticSearchBundle\Transformer;

use Networking\InitCmsBundle\Entity\Media;

class IndexableChecker
{
    public static function isIndexable($object)
    {
        if ($object instanceof Media) {
        	return $object->getEnabled();
        }
        return false;
    }
}
