<?php
/**
 * This file is part of the billag package.
 *
 * (c) net working AG <info@networking.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 

namespace Networking\ElasticSearchBundle\Component;

use FOs\ElasticaBundle\Persister\ObjectPersisterInterface;
/**
 * @author Yorkie Chadwick <y.chadwick@networking.ch>
 */
interface ObjectPersisterAwareInterface {

    public function setObjectPersister(ObjectPersisterInterface $objectPersister);

}