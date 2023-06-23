<?php

declare(strict_types=1);

namespace Networking\ElasticSearchBundle\Tika;

use Symfony\Component\Process\Process;
use SplFileInfo;

class TikaClient {

    public static function prepareClient(){
        $tikaPath = __DIR__ . "/../bin/tika-app-2.3.0.jar";

        return \Vaites\ApacheTika\Client::prepare($tikaPath);

    }

}
