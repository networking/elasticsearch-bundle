<?php
/**
 * This file is part of the forel package.
 *
 * (c) net working AG <info@networking.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Networking\ElasticSearchBundle\Entity;

use Networking\InitCmsBundle\Entity\BaseText,
    Doctrine\ORM\Mapping as ORM,
    Ibrows\Bundle\SonataAdminAnnotationBundle\Annotation as Sonata;

/**
 * @author Yorkie Chadwick <y.chadwick@networking.ch>
 *
 *
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Table(name="searchable_text")
 * @ORM\Entity()
 *
 */
class SearchableText extends BaseText
{
    /**
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @return string
     */
    public function getSearchableContent()
    {
        return strip_tags($this->getText());
    }

    /**
     * @return string
     */
    public function getContentTypeName()
    {
        return 'Searchable Text Block';
    }
}
