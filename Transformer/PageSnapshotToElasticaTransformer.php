<?php
/**
 * This file is part of the billag package.
 *
 * (c) net working AG <info@networking.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Networking\ElasticSearchBundle\Transformer;

use FOS\ElasticaBundle\Transformer\ModelToElasticaTransformerInterface;
use JMS\Serializer\Serializer;
use Networking\InitCmsBundle\Entity\PageSnapshot;
use Symfony\Component\Form\Util\PropertyPath;

/**
 * @author Yorkie Chadwick <y.chadwick@networking.ch>
 */
class PageSnapshotToElasticaTransformer implements ModelToElasticaTransformerInterface
{
    /**
     * Optional parameters
     *
     * @var array
     */
    protected $options = array(
        'identifier' => 'id'
    );

    /**
     * @var Serializer
     */
    protected $serializer;

    /**
     * Instanciates a new Mapper
     *
     * @param \JMS\Serializer\Serializer $serializer
     * @param array $options
     */
    public function __construct(Serializer $serializer, array $options = array())
    {
        $this->serializer = $serializer;
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Transforms an PageSnapshot object into an elastica object having the required keys
     *
     * @param PageSnapshot $object the object to convert
     * @param array $fields the keys we want to have in the returned array
     *
     * @return \Elastica_Document
     **/
    public function transform($object, array $fields)
    {

        $content = array();
        /** @var $page  \Networking\InitCmsBundle\Entity\Page */
        $page = $this->serializer->deserialize(
            $object->getVersionedData(),
            'Networking\InitCmsBundle\Entity\Page',
            'json'
        );

        foreach ($page->getLayoutBlock() as $layoutBlock) {
            $contentItem = $this->serializer->deserialize(
                $layoutBlock->getSnapshotContent(),
                $layoutBlock->getClassType(),
                'json'
            );

            if (is_object($contentItem) && method_exists($contentItem, 'getSearchableContent')) {
                $content[] = html_entity_decode($contentItem->getSearchableContent(), null, 'UTF-8');
            }
        }

        $identifierProperty = new PropertyPath($this->options['identifier']);
        $identifier = $identifierProperty->getValue($page);


        $document = new \Elastica_Document($identifier);

        foreach ($fields as $key => $mapping) {
            $property = new PropertyPath($key);
            if (!empty($mapping['_parent']) && $mapping['_parent'] !== '~') {
                $parent = $property->getValue($page);
                $identifierProperty = new PropertyPath($mapping['_parent']['identifier']);
                $document->setParent($identifierProperty->getValue($parent));
            } else if (isset($mapping['type']) && in_array($mapping['type'], array('nested', 'object'))) {
                $submapping = $mapping['properties'];
                $subcollection = $property->getValue($page);
                $document->add($key, $this->transformNested($subcollection, $submapping, $document));
            } else if (isset($mapping['type']) && $mapping['type'] == 'attachment') {
                $attachment = $property->getValue($page);
                if ($attachment instanceof \SplFileInfo) {
                    $document->addFile($key, $attachment->getPathName());
                } else {
                    $document->addFileContent($key, $attachment);
                }
            } else {

                switch($key){
                    case 'name':
                        $value = $page->getPageName();
                        break;
                    case 'content':
                        $value = implode("\n", $content);
                        break;
                    case 'url':
                        $value = $object->getPath();
                        break;
                    case 'type':
                        $value = 'Page';
                        break;
                    default:
                        $value = $property->getValue($page);
                        break;
                }
                $document->add($key, $this->normalizeValue($value));
            }
        }
        $document->add('type', 'Page');

        return $document;
    }

    /**
     * transform a nested document or an object property into an array of ElasticaDocument
     *
     * @param array $objects    the object to convert
     * @param array $fields     the keys we want to have in the returned array
     * @param \Elastica_Document $parent the parent document
     *
     * @return array
     */
    protected function transformNested($objects, array $fields, $parent)
    {
        if (is_array($objects) || $objects instanceof \Traversable || $objects instanceof \ArrayAccess) {
            $documents = array();
            foreach ($objects as $object) {
                $document = $this->transform($object, $fields);
                $documents[] = $document->getData();
            }

            return $documents;
        } elseif (null !== $objects) {
            $document = $this->transform($objects, $fields);

            return $document->getData();
        }

        return array();
    }

    /**
     * Attempts to convert any type to a string or an array of strings
     *
     * @param mixed $value
     *
     * @return string|array
     */
    protected function normalizeValue($value)
    {
        $normalizeValue = function (&$v) {
            if ($v instanceof \DateTime) {
                $v = $v->format('c');
            } elseif (!is_scalar($v) && !is_null($v)) {
                $v = (string)$v;
            }
        };

        if (is_array($value) || $value instanceof \Traversable || $value instanceof \ArrayAccess) {
            $value = is_array($value) ? $value : iterator_to_array($value);
            array_walk_recursive($value, $normalizeValue);
        } else {
            $normalizeValue($value);
        }

        return $value;
    }

}
