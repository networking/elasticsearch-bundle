<?php

declare(strict_types=1);

/**
 * This file is part of the forel package.
 *
 * (c) net working AG <info@networking.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Networking\ElasticSearchBundle\Transformer;

use Doctrine\Persistence\ManagerRegistry;
use Elastica\Document;
use FOS\ElasticaBundle\Transformer\ModelToElasticaTransformerInterface;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerInterface;
use Networking\ElasticSearchBundle\Model\SearchableContentInterface;
use Networking\InitCmsBundle\Entity\PageSnapshot;
use Networking\InitCmsBundle\Helper\PageHelper;
use Networking\InitCmsBundle\Model\TextInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyPath;

/**
 * @author Yorkie Chadwick <y.chadwick@networking.ch>
 */
class PageSnapshotToElasticaTransformer implements ModelToElasticaTransformerInterface
{
    /**
     * Optional parameters.
     *
     * @var array
     */
    protected $options = [
        'identifier' => 'id',
    ];

    /**
     * @var Serializer
     */
    protected $serializer;

    /**
     * @var PageHelper
     */
    protected $pageHelper;

    /**
     * @var ManagerRegistry
     */
    protected $managerRegistry;

    public function __construct(
        SerializerInterface $serializer,
        PageHelper $pageHelper,
        ManagerRegistry $managerRegistry
    ) {
        $this->serializer = $serializer;
        $this->pageHelper = $pageHelper;
        $this->managerRegistry = $managerRegistry;
    }

    /**
     * Transforms an PageSnapshot object into an elastica object having the required keys.
     *
     * @param PageSnapshot $object the object to convert
     * @param array $fields the keys we want to have in the returned array
     *
     * @return Document
     **/
    public function transform(object $object, array $fields): Document
    {
        $content = [];
        $page = $object->getPage();

        $page = $this->pageHelper->unserializePageSnapshotData($object, true);
        foreach ($page->getLayoutBlock() as $layoutBlock) {

            $classImplements = class_implements($layoutBlock->getClassType());
            $classHasMethod = method_exists($layoutBlock->getClassType(), 'getSearchableContent');
            if(!array_key_exists(SearchableContentInterface::class, $classImplements) && !$classHasMethod){
                continue;
            }

            $contentItem = $this->serializer->deserialize(
                $layoutBlock->getSnapshotContent(),
                $layoutBlock->getClassType(),
                'json'
            );

            if($this->managerRegistry->getManagerForClass($contentItem::class)->contains($contentItem)){
                $this->managerRegistry->getManagerForClass($contentItem::class)->refresh($contentItem);
            }

            $content[] = html_entity_decode(string: (string) $contentItem->getSearchableContent(), encoding:  'UTF-8');

        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $identifierProperty = new PropertyPath($this->options['identifier']);
        $identifier = $propertyAccessor->getValue($page, $identifierProperty);
        $document = new Document((string) $identifier);

        foreach ($fields as $key => $mapping) {
            $property = new PropertyPath($key);
            if (!empty($mapping['_parent']) && $mapping['_parent'] !== '~') {
                $parent = $propertyAccessor->getValue($page, $property);

                $identifierProperty = new PropertyPath($mapping['_parent']['identifier']);
                $document->setParent($propertyAccessor->getValue($parent, $identifierProperty));
            } elseif (isset($mapping['type']) && in_array($mapping['type'], ['nested', 'object'])) {
                $submapping = $mapping['properties'];
                $subcollection = $propertyAccessor->getValue($page, $property);
                $document->set($key, $this->transformNested($subcollection, $submapping, $document));
            } elseif (isset($mapping['type']) && $mapping['type'] == 'attachment') {
                $attachment = $property->getElement($page);
                if ($attachment instanceof \SplFileInfo) {
                    $document->addFile($key, $attachment->getPathName());
                } else {
                    $document->addFileContent($key, $attachment);
                }
            } else {
                $value = match ($key) {
                    'name' => $page->getPageName(),
                    'content' => implode("\n", $content),
                    'url' => $object->getPath(),
                    'type' => 'Page',
                    default => $propertyAccessor->getValue($page, $property),
                };
                $document->set($key, $this->normalizeValue($value));
            }
        }
        $document->set('type', 'Page');

        return $document;
    }

    /**
     * transform a nested document or an object property into an array of ElasticaDocument.
     *
     * @param array $objects the object to convert
     * @param array $fields the keys we want to have in the returned array
     * @param Document $parent the parent document
     *
     * @return array
     */
    protected function transformNested($objects, array $fields, $parent)
    {
        if (is_iterable($objects) || $objects instanceof \ArrayAccess) {
            $documents = [];
            foreach ($objects as $object) {
                $document = $this->transform($object, $fields);
                $documents[] = $document->getData();
            }

            return $documents;
        } elseif (null !== $objects) {
            $document = $this->transform($objects, $fields);

            return $document->getData();
        }

        return [];
    }

    /**
     * Attempts to convert any type to a string or an array of strings.
     *
     *
     */
    protected function normalizeValue(mixed $value): string|array|null
    {
        $normalizeValue = function (&$v) {
            if ($v instanceof \DateTime) {
                $v = $v->format('c');
            } elseif (!is_scalar($v) && !is_null($v)) {
                $v = (string)$v;
            }
        };

        if (is_iterable($value) || $value instanceof \ArrayAccess) {
            $value = is_array($value) ? $value : iterator_to_array($value);
            array_walk_recursive($value, $normalizeValue);
        } else {
            $normalizeValue($value);
        }

        return $value;
    }
}
