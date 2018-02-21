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
use Networking\InitCmsBundle\Entity\Media;
use Sonata\MediaBundle\Provider\Pool;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Routing\RouterInterface;

/**
 * @author Yorkie Chadwick <y.chadwick@networking.ch>
 */
class MediaToElasticaTransformer implements ModelToElasticaTransformerInterface
{

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var Pool
     */
    protected $pool;

    /**
     * @var string
     */
    protected $path;

    /**
     * Optional parameters
     *
     * @var array
     */
    protected $options = [
        'identifier' => 'id'
    ];

    /**
     * MediaToElasticaTransformer constructor.
     * @param RouterInterface $router
     * @param Pool $pool
     * @param $path
     */
    public function __construct(RouterInterface $router, Pool $pool, $path)
    {
        $this->router = $router;
        $this->pool = $pool;
        $this->path = $path;
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

        $propertyAccessor = new PropertyAccessor();
        $identifier =  $propertyAccessor->getValue($object, $this->options['identifier']);
        $document = new Document($identifier);


        $provider = $this->pool->getProvider($object->getProviderName());


        foreach ($fields as $key => $mapping) {
            $value = null;

            if (!empty($mapping['_parent']) && $mapping['_parent'] !== '~') {
                $parent = $propertyAccessor->getValue($object, $key); //$property->getValue($object);

                $identifierProperty = new PropertyAccessor();
                $document->setParent($identifierProperty->getValue($parent, $mapping['_parent']['identifier']));
                //$identifierProperty = new PropertyPath($mapping['_parent']['identifier']);
                //$document->setParent($identifierProperty->getValue($parent));
            } else if (isset($mapping['type']) && in_array($mapping['type'], ['nested', 'object'])) {
                $submapping = $mapping['properties'];
                $subcollection = $propertyAccessor->getValue($object, $key);
                $document->set($key, $this->transformNested($subcollection, $submapping, $document));
            } else if (isset($mapping['type']) && $mapping['type'] == 'attachment') {

                $file = $provider->generatePublicUrl($object, 'reference');
                $attachment = new \SplFileInfo($this->path . $file);
                $document->addFile($key, $attachment->getPathName(), $object->getContentType());

            } else {

                switch ($key) {
                    case 'content':
                        $file = $provider->generatePublicUrl($object, 'reference');
                        $value = PdfDocumentExtractor::extract($this->path . $file);
                        break;
                    case 'locale':
                        $value = $object->getLocale();
                        break;
                    case 'url':
                        $value = $this->router->generate('sonata_media_download', ['id' => $object->getId()]);
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
            $documents = [];
            foreach ($objects as $object) {
                $document = $this->transform($object, $fields);
                $documents[] = $document->getData();
            }

            return $documents;
        } elseif (null !== $objects && $objects instanceof Media) {
            $document = $this->transform($objects, $fields);

            return $document->getData();
        }

        return [];
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
