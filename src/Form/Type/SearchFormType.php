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
namespace Networking\ElasticSearchBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @author Yorkie Chadwick <y.chadwick@networking.ch>
 */
class SearchFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $searchTerm = '';
        if (array_key_exists('networking_elastic_search', $_REQUEST)) {
            $searchTerm = $_REQUEST['networking_elastic_search']['search'];
        }
        $builder
            ->setAttribute('render_fieldset', false)
            ->setAttribute('label_render', false)
            ->setAttribute('show_legend', false)
            ->add('search', 'text', [
                'widget_control_group' => false,
                'widget_controls' => false,
                'attr' => [
                    'class' => 'input-medium search-query',
                ],
                'data' => $searchTerm,
            ])
        ;
    }
    public function getName(): string
    {
        return 'networking_elastic_search';
    }
    /**
     * To implement NavbarFormTypeInterface.
     */
    public function getRoute(): string
    {
        return 'site_search'; // return here the name of the route the form should point to
    }
}
