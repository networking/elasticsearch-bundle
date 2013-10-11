<?php
/**
 * This file is part of the billag package.
 *
 * (c) net working AG <info@networking.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Networking\ElasticSearchBundle\Entity;

use Networking\InitCmsBundle\Entity\BaseText,
    Doctrine\ORM\Mapping as ORM,
    Networking\InitCmsBundle\Entity\ContentInterface,
    Ibrows\Bundle\SonataAdminAnnotationBundle\Annotation as Sonata;

/**
 * @author Yorkie Chadwick <y.chadwick@networking.ch>
 *
 *
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Table(name="searchable_text")
 * @ORM\Entity(repositoryClass="Networking\InitCmsBundle\Entity\TextRepository")
 *
 */
class SearchableText extends BaseText
{
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
