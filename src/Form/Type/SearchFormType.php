<?php
/**
 * This file is part of the forel package.
 *
 * (c) net working AG <info@networking.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Networking\ElasticSearchBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Mopa\Bundle\BootstrapBundle\Navbar\NavbarFormInterface;
/**
 * @author Yorkie Chadwick <y.chadwick@networking.ch>
 */
class SearchFormType extends AbstractType implements NavbarFormInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $searchTerm = '';
        if(array_key_exists('networking_elastic_search', $_REQUEST)){
            $searchTerm = $_REQUEST['networking_elastic_search']['search'];
        }
        $builder
            ->setAttribute('render_fieldset', false)
            ->setAttribute('label_render', false)
            ->setAttribute('show_legend', false)
            ->add('search', 'text', array(
                'widget_control_group' => false,
                'widget_controls' => false,
                'attr' => array(
                    'class' => "input-medium search-query"
                ),
                'data' => $searchTerm
            ))
        ;
    }
    public function getName()
    {
        return 'networking_elastic_search';
    }
    /**
     * To implement NavbarFormTypeInterface
     */
    public function getRoute()
    {
        return "site_search"; # return here the name of the route the form should point to
    }
}