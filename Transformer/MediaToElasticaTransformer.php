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
use Symfony\Component\DependencyInjection\Container;
use Networking\InitCmsBundle\Entity\Media;
//use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * @author Yorkie Chadwick <y.chadwick@networking.ch>
 */
class MediaToElasticaTransformer implements ModelToElasticaTransformerInterface
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
     * @var Container
     */
    protected $container;

    /**
     * Instanciates a new Mapper
     *
     * @param array $options
     */
    public function __construct(Container $container, array $options = array())
    {
        $this->options = array_merge($this->options, $options);
        $this->container = $container;
    }

    /**
     * Transforms an Media object into an elastica object having the required keys
     *
     * @param Media $object the object to convert
     * @param array $fields the keys we want to have in the returned array
     *
     * @return Document
     **/
    public function transform($object, array $fields)
    {
        $content = array();


//        $identifierProperty = new PropertyPath($this->options['identifier']);
//        $identifier = $identifierProperty->getValue($object);

        $propertyAccessor = new PropertyAccessor();
        $identifier =  $propertyAccessor->getValue($object, $this->options['identifier']);
        $document = new Document($identifier);

        $provider = $this->container->get($object->getProviderName());

        foreach ($fields as $key => $mapping) {
            $value = null;

            if (!empty($mapping['_parent']) && $mapping['_parent'] !== '~') {
                $parent = $propertyAccessor->getValue($object, $key); //$property->getValue($object);

                $identifierProperty = new PropertyAccessor();
                $document->setParent($identifierProperty->getValue($parent, $mapping['_parent']['identifier']));
                //$identifierProperty = new PropertyPath($mapping['_parent']['identifier']);
                //$document->setParent($identifierProperty->getValue($parent));
            } else if (isset($mapping['type']) && in_array($mapping['type'], array('nested', 'object'))) {
                $submapping = $mapping['properties'];
                $subcollection = $propertyAccessor->getValue($object, $key);
                $document->set($key, $this->transformNested($subcollection, $submapping, $document));
            } else if (isset($mapping['type']) && $mapping['type'] == 'attachment') {

                $path = $this->container->get('kernel')->getRootDir() . '/../web';
                $file = $provider->generatePublicUrl($object, 'reference');

                $attachment = new \SplFileInfo($path . $file);
                $document->addFile($key, $attachment->getPathName(), $object->getContentType());

            } else {

                switch ($key) {
                    case 'content':
                        $path = $this->container->get('kernel')->getRootDir() . '/../web';
                        $file = $provider->generatePublicUrl($object, 'reference');
                        $value = PdfDocumentExtractor::extract($path . $file);
                        break;
                    case 'locale':
                        $value = $object->getLocale();
                        break;
                    case 'url':
                        $value = $this->container->get('router')->generate('sonata_media_download', array('id' => $object->getId()));
                        break;
                    case 'metaTitle':
                        $value = '';
                        break;
                    case 'type':
                        $document->set('type', 'PDF');
                        break;
                    default:
                        $value = $propertyAccessor->getValue($object, $key);  //$property->getValue($object);
                        break;
                }
                $document->set($key, $this->normalizeValue($value));
            }
        }

        $document->set('type', 'PDF');
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
        } elseif (null !== $objects && $objects instanceof Media) {
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
