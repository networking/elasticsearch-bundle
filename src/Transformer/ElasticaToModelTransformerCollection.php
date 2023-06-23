<?php

declare(strict_types=1);

namespace Networking\ElasticSearchBundle\Transformer;

use Elastica\Result;
use FOS\ElasticaBundle\HybridResult;
use FOS\ElasticaBundle\Transformer\ElasticaToModelTransformerInterface;
use Networking\ElasticSearchBundle\Elastica\MultiIndex;

/**
 * Holds a collection of transformers for an index wide transformation.
 *
 * @author Tim Nagel <tim@nagel.com.au>
 * @author Insekticid <insekticid+fos@exploit.cz>
 */
class ElasticaToModelTransformerCollection implements ElasticaToModelTransformerInterface
{
    /**
     * @var ElasticaToModelTransformerInterface[]
     */
    protected $transformers = [];

    /**
     * @var MultiIndex
     */
    protected $index;

    /**
     * @param ElasticaToModelTransformerInterface[]|\Traversable $transformers
     */
    public function __construct(MultiIndex $index, $transformers)
    {
        $transformers = iterator_to_array($transformers);

        if(!$index->getName()){
            $this->transformers = $transformers;
        }


        if($index->getName()){
            foreach ($transformers as $key => $transformer){
                $this->transformers[$index->getName().$key] = $transformer;
            }
        }

        $this->index = $index;
    }

    /**
     * {@inheritdoc}
     */
    public function getObjectClass(): string
    {
        return implode(',', array_map(fn(ElasticaToModelTransformerInterface $transformer) => $transformer->getObjectClass(), $this->transformers));
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifierField(): string
    {
        return array_map(fn(ElasticaToModelTransformerInterface $transformer) => $transformer->getIdentifierField(), $this->transformers)[0];
    }

    /**
     * {@inheritdoc}
     */
    public function transform(array $elasticaObjects): array
    {

        $sorted = [];
        /** @var Result $object */
        foreach ($elasticaObjects as $object) {

            $indexName = $this->matchIndexName($object->getIndex());
            $sorted[$indexName][] = $object;
        }

        $transformed = [];

        foreach ($sorted as $type => $objects) {
            $transformedObjects = $this->transformers[$type]->transform($objects);
            $identifierGetter = 'get'.ucfirst($this->transformers[$type]->getIdentifierField());
            $transformed[$type] = array_combine(
                array_map(
                    fn($o) => $o->$identifierGetter(),
                    $transformedObjects
                ),
                $transformedObjects
            );
        }

        $result = [];
        foreach ($elasticaObjects as $object) {
            $indexName = $this->matchIndexName($object->getIndex());
            if (array_key_exists((string) $object->getId(), $transformed[$indexName])) {
                $result[] = $transformed[$indexName][(string) $object->getId()];
            }
        }

        return $result;
    }

    public function matchIndexName($name)
    {
        foreach ($this->index->getIndices() as $index){
            if(str_starts_with((string) $name, (string) $index)){
                return $index;
            }
        }

        return $name;
    }

    /**
     * {@inheritdoc}
     */
    public function hybridTransform(array $elasticaObjects): array
    {
        $objects = $this->transform($elasticaObjects);

        $result = [];
        for ($i = 0, $j = count($elasticaObjects); $i < $j; ++$i) {
            if (!isset($objects[$i])) {
                continue;
            }
            $result[] = new HybridResult($elasticaObjects[$i], $objects[$i]);
        }

        return $result;
    }
}
