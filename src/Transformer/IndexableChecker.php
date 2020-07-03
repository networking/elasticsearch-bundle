<?php
/**
 * Created by PhpStorm.
 * User: yorkie
 * Date: 19.02.18
 * Time: 09:36.
 */

namespace Networking\ElasticSearchBundle\Transformer;

use Networking\InitCmsBundle\Entity\Media;
use Networking\InitCmsBundle\Entity\PageSnapshot;

class IndexableChecker
{
    public static function isIndexable($object)
    {
        if ($object instanceof Media) {
        	return $object->getEnabled();
        }


        if($object instanceof PageSnapshot){

            if($object->getPage()->getStatus() == 'status_published' and $object->getPage()->getVisibility() == 'public' ){
                return true;
            }
        }



        return false;
    }
}
