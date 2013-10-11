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
use Symfony\Component\DependencyInjection\Container;
use Networking\InitCmsBundle\Entity\Media;
use Symfony\Component\Form\Util\PropertyPath;

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
     * @return \Elastica_Document
     **/
    public function transform($object, array $fields)
    {
        $content = array();


        $identifierProperty = new PropertyPath($this->options['identifier']);
        $identifier = $identifierProperty->getValue($object);
        $document = new \Elastica_Document($identifier);

        $provider = $this->container->get($object->getProviderName());

        foreach ($fields as $key => $mapping) {
            $value = null;

            $property = new PropertyPath($key);
            if (!empty($mapping['_parent']) && $mapping['_parent'] !== '~') {
                $parent = $property->getValue($object);
                $identifierProperty = new PropertyPath($mapping['_parent']['identifier']);
                $document->setParent($identifierProperty->getValue($parent));
            } else if (isset($mapping['type']) && in_array($mapping['type'], array('nested', 'object'))) {
                $submapping = $mapping['properties'];
                $subcollection = $property->getValue($object);
                $document->add($key, $this->transformNested($subcollection, $submapping, $document));
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

                        if($value){
                            $value = iconv(mb_detect_encoding($value, null, true), "UTF-8//TRANSLIT", $value);
                        }

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
                        $document->add('type', 'PDF');
                        break;
                    default:
                        $value = $property->getValue($object);
                        break;
                }
                $document->add($key, $this->normalizeValue($value));
            }
        }
        $document->add('type', 'PDF');
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
