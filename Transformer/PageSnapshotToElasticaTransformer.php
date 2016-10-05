<?php
/**
 * This file is part of the forel package.
 *
 * (c) net working AG <info@networking.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Networking\ElasticSearchBundle\Transformer;

use Elastica\Document;
use FOS\ElasticaBundle\Transformer\ModelToElasticaTransformerInterface;
use JMS\Serializer\Serializer;
use Networking\InitCmsBundle\Entity\PageSnapshot;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyPath;

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
     * @return Document
     **/
    public function transform($object, array $fields)
    {

        $content = array();
        /** @var $page  \Application\Networking\InitCmsBundle\Entity\Page */
        $page = $this->serializer->deserialize(
            $object->getVersionedData(),
            'Application\Networking\InitCmsBundle\Entity\Page',
            'json'
        );

        foreach ($page->getLayoutBlock() as $layoutBlock) {



            $contentItem = $this->serializer->deserialize(
                $layoutBlock->getSnapshotContent(),
                $layoutBlock->getClassType(),
                'json'
            );



            //var_dump($contentItem);

            if (is_object($contentItem) && method_exists($contentItem, 'getSortableTextBlocks')) {

                $sortableTextBlocks = $contentItem->getSortableTextBlocks();
                foreach ($sortableTextBlocks as $block) {
                    $text = strip_tags( $block->getText());
                    $content[] = html_entity_decode($text, null, 'UTF-8');
                    //echo $block->getText();
                }


                //$content[] = html_entity_decode($contentItem->getSearchableContent(), null, 'UTF-8');
            }
        }

        $identifierProperty = new PropertyPath($this->options['identifier']);

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $identifier = $propertyAccessor->getValue($page, $identifierProperty);


        $document = new Document($identifier);

        foreach ($fields as $key => $mapping) {
            $property = new PropertyPath($key);
            if (!empty($mapping['_parent']) && $mapping['_parent'] !== '~') {

                $parent = $propertyAccessor->getValue($page, $property);

                $identifierProperty = new PropertyPath($mapping['_parent']['identifier']);
                $document->setParent($propertyAccessor->getValue($parent, $identifierProperty));

            } else if (isset($mapping['type']) && in_array($mapping['type'], array('nested', 'object'))) {
                $submapping = $mapping['properties'];
                $subcollection = $propertyAccessor->getValue($page, $property);
                $document->set($key, $this->transformNested($subcollection, $submapping, $document));
            } else if (isset($mapping['type']) && $mapping['type'] == 'attachment') {
                $attachment = $property->getElement($page);
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
                        $value = $propertyAccessor->getValue($page, $property);
                        break;
                }
                $document->set($key, $this->normalizeValue($value));
            }
        }
        $document->set('type', 'Page');

        return $document;
    }

    /**
     * transform a nested document or an object property into an array of ElasticaDocument
     *
     * @param array $objects    the object to convert
     * @param array $fields     the keys we want to have in the returned array
     * @param Document $parent the parent document
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
