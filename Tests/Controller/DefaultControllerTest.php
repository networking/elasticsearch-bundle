<?php

namespace Networking\ElasticSearchBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerTest extends WebTestCase
{
    public function testIndex()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/search/');

        $this->assertTrue($crawler->filter('html:contains("no.search.results")')->count() > 0);
    }
}
