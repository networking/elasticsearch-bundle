<?php
/**
 * This file is part of the forel package.
 *
 * (c) net working AG <info@networking.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Networking\ElasticSearchBundle\Component;

use FOS\ElasticaBundle\Provider\ProviderInterface;
use FOS\ElasticaBundle\Transformer\ModelToElasticaAutoTransformer;
use Networking\ElasticSearchBundle\Transformer\NewsToElasticaTransformer;
use Networking\ElasticSearchBundle\Transformer\PageSnapshotToElasticaTransformer;
use Networking\ElasticSearchBundle\Transformer\MediaToElasticaTransformer;
use FOS\ElasticaBundle\Persister\ObjectPersister;
use Application\Networking\InitCmsBundle\Entity\News;
use Symfony\Component\DependencyInjection\Container;
use Networking\ElasticSearchBundle\Component\ObjectPersisterAwareInterface;

/**
 * @author Yorkie Chadwick <y.chadwick@networking.ch>
 */
class Configurator
{

    protected $listener;

    protected $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function configurePageSnapshot(ObjectPersisterAwareInterface $object)
    {
        $transformer = new PageSnapshotToElasticaTransformer($this->container->get('serializer'));
        $indexName = $this->container->getParameter('elastic_search_index');
        $type = $this->container->get(sprintf('fos_elastica.index.%s.page', $indexName));

        $mapping = $type->getMapping();

        if(isset($mapping[$type->getName()]['properties'])){

            $fields = $mapping[$type->getName()]['properties'];

        }
        else{

            $fields = $mapping[$indexName]['mappings'][$type->getName()]['properties'];

        }

        $persister = new ObjectPersister($type, $transformer, 'Networking\InitCmsBundle\Entity\PageSnapshot', $fields);

        $object->setObjectPersister($persister);
    }

    public function configureMedia(ObjectPersisterAwareInterface $object)
    {

        $transformer = new MediaToElasticaTransformer($this->container);

        $indexName = $this->container->getParameter('elastic_search_index');
        $type = $this->container->get(sprintf('fos_elastica.index.%s.media', $indexName));

        $mapping = $type->getMapping();


        if(isset($mapping[$type->getName()]['properties'])){

            $fields = $mapping[$type->getName()]['properties'];

        }
        else{

            $fields = $mapping[$indexName]['mappings'][$type->getName()]['properties'];

        }
        $persister = new ObjectPersister($type, $transformer, 'Networking\InitCmsBundle\Entity\Media', $fields);

        $object->setObjectPersister($persister);
    }


}
