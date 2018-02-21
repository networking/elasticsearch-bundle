<?php

namespace Networking\ElasticSearchBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Networking\ElasticSearchBundle\DependencyInjection\Compiler\PageSnapshotPass;

class NetworkingElasticSearchBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
    }
}
