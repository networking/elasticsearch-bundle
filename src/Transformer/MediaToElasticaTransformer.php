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

use Elastica\Document;
use FOS\ElasticaBundle\Transformer\ModelToElasticaTransformerInterface;
use Networking\ElasticSearchBundle\Tika\TikaClient;
use Networking\InitCmsBundle\Entity\Media;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\BaseReader;
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
     * Optional parameters.
     *
     * @var array
     */
    protected $options = [
        'identifier' => 'id',
    ];

    /**
     * MediaToElasticaTransformer constructor.
     *
     * @param $path
     * @param string $path
     */
    public function __construct(RouterInterface $router, Pool $pool, protected $path)
    {
        $this->router = $router;
        $this->pool = $pool;
    }

    /**
     * Transforms an Media object into an elastica object having the required keys.
     *
     * @param Media $object the object to convert
     * @param array $fields the keys we want to have in the returned array
     *
     * @return Document
     **/
    public function transform(object $object, array $fields): Document
    {
        $propertyAccessor = new PropertyAccessor();
        $identifier = $propertyAccessor->getValue($object, $this->options['identifier']);
        $document = new Document((string) $identifier);

        $provider = $this->pool->getProvider($object->getProviderName());

        foreach ($fields as $key => $mapping) {
            $value = null;

            if (!empty($mapping['_parent']) && $mapping['_parent'] !== '~') {
                $parent = $propertyAccessor->getValue($object, $key); //$property->getValue($object);

                $identifierProperty = new PropertyAccessor();
                $document->setParent($identifierProperty->getValue($parent, $mapping['_parent']['identifier']));
                //$identifierProperty = new PropertyPath($mapping['_parent']['identifier']);
                //$document->setParent($identifierProperty->getValue($parent));
            } elseif (isset($mapping['type']) && in_array($mapping['type'], ['nested', 'object'])) {
                $submapping = $mapping['properties'];
                $subcollection = $propertyAccessor->getValue($object, $key);
                $document->set($key, $this->transformNested($subcollection, $submapping, $document));
            } elseif (isset($mapping['type']) && $mapping['type'] == 'attachment') {
                if($provider->getFilesystem()->getAdapter() instanceof \Sonata\MediaBundle\Filesystem\Local){
                    $file = $provider->getFilesystem()->getAdapter()->getDirectory().DIRECTORY_SEPARATOR.$provider->generatePrivateUrl($object, 'reference');
                }else{
                    $file = $provider->generatePublicUrl($object, 'reference');
                }
                $attachment = new \SplFileInfo($file);
                $document->addFile($key, $attachment->getPathName(), $object->getContentType());
            } else {
                switch ($key) {
                    case 'content':

                        if($provider->getFilesystem()->getAdapter() instanceof \Sonata\MediaBundle\Filesystem\Local){
                            $file = $provider->getFilesystem()->getAdapter()->getDirectory().DIRECTORY_SEPARATOR.$provider->generatePrivateUrl($object, 'reference');
                        }else{
                            $file = $provider->generatePublicUrl($object, 'reference');
                        }

                        if('application/pdf' == $object->getContentType()){
                            $value = PdfDocumentExtractor::extract($file);
                        }

                        if(str_ends_with($file, 'docx')){
                            $value = $this->readDocx($file);
                        }

                        if(str_ends_with($file, 'doc')){
                            $value = $this->readDoc($file);
                        }

                        if(str_ends_with($file, 'xlsx')){
                            $value = $this->readXlsx($file);
                        }

                        if(str_ends_with($file, 'xls')){
                            $value = $this->readXls($file);
                        }

                        if(!$value){
                            $tikaClient = TikaClient::prepareClient();
                            try {
                                $value = $tikaClient->getMainText($file);

                            }catch (\Exception){
                                $value = $object->getDescription();
                            }
                        }
                        break;
                    case 'locale':
                        $value = $object->getLocale();
                        break;
                    case 'url':
                        $value = $this->router->generate('sonata_media_download', ['id' => $object->getId()]);
                        break;
                    case 'metaTitle':
                        $value = $object->getAuthorName().' '.$object->getCopyright();
                        break;
                    case 'type':
                        $document->set('type', $object->getContentType());
                        break;
                    default:
                        $value = $propertyAccessor->getValue($object, $key);  //$property->getValue($object);
                        break;
                }

                $document->set($key, $this->normalizeValue($value));
            }
        }

        $document->set('type', $object->getContentType());

        return $document;
    }

    /**
     * transform a nested document or an object property into an array of ElasticaDocument.
     *
     * @param array    $objects the object to convert
     * @param array    $fields  the keys we want to have in the returned array
     * @param Document $parent  the parent document
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
        } elseif (null !== $objects && $objects instanceof Media) {
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
                $v = (string) $v;
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

    /**
     * @param $file
     *
     * @return null|string|string[]
     */
    protected function readDoc($file): null|string|array {
        $fileHandle = fopen($file, "r");
        $line = @fread($fileHandle, filesize($file));
        $lines = explode(chr(0x0D),$line);
        $outtext = "";
        foreach($lines as $thisline)
        {
            $pos = strpos($thisline, chr(0x00));
            if (($pos !== FALSE)||(strlen($thisline)==0))
            {
            } else {
                $outtext .= $thisline." ";
            }
        }
        $outtext = preg_replace("/[^a-zA-Z0-9\s\,\.\-\n\r\t@\/\_\(\)]/","",$outtext);
        return $outtext;
    }

    /**
     * @param $file
     */
    protected function readDocx($file): bool|string{

        $content = '';

        $zip = zip_open($file);

        if (!$zip || is_numeric($zip)) return false;

        while ($zip_entry = zip_read($zip)) {

            if (zip_entry_open($zip, $zip_entry) == FALSE) continue;

            if (zip_entry_name($zip_entry) != "word/document.xml") continue;

            $content .= zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));

            zip_entry_close($zip_entry);
        }// end while

        zip_close($zip);

        $content = str_replace('</w:r></w:p></w:tc><w:tc>', " ", $content);
        $content = str_replace('</w:r></w:p>', "\r\n", $content);
        $striped_content = strip_tags($content);

        return $striped_content;
    }

    /**
     * @param $file
     *
     * @return string
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    protected function readXlsx($file)
    {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        return $this->readExcel($reader, $file);

    }

    /**
     * @param $file
     *
     * @return string
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    protected function readXls($file)
    {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
        return $this->readExcel($reader, $file);
    }

    /**
     * @param $file
     *
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    protected function readExcel(BaseReader $reader, $file): string
    {
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file);

        $data = [];

        foreach ($spreadsheet->getAllSheets() as $sheet){
            foreach ($sheet->toArray() as $row){
                $data[] = implode(', ', $row);
            }
        }

        return implode(', ', $data);
    }
}
