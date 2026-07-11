<?php

use SPP\Core\RestSerializer;

class RestSerializerTest extends \SPP\Parikshak\TestCase
{
    public function testSerializeJson()
    {
        $data = ['foo' => 'bar'];
        $serializer = new RestSerializer();
        $serializer->setFormat('json');
        $output = $serializer->serialize($data);

        $this->assertStringContainsString('"foo":"bar"', $output);
    }
}
