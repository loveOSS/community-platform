<?php

namespace tests\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Is the Homepage available?
 */
class MinimalControllerTest extends WebTestCase
{
    private $client;

    protected function setUp()
    {
        $this->client = $this->createClient();
    }

    public function testHomepageOk()
    {
        $this->client->request('HEAD', '/');
        $response = $this->client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
    }
}
