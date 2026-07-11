<?php

use SPP\Core\AppDirectoryResolver;

class AppDirectoryResolverTest extends \SPP\Parikshak\TestCase
{
    public function testResolveDirectories()
    {
        $app = 'testapp';
        $paths = AppDirectoryResolver::resolveDirectories($app);

        $this->assertIsArray($paths);
        $this->assertArrayHasKey('app_dir', $paths);
        $this->assertEquals(SPP_APP_DIR . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $app, $paths['app_dir']);
    }
}
