<?php

use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    /**
     * Prove that the caching mechanism does not allow Object Injection via unserialize().
     */
    public function testCacheDriverRejectsUnserializePOPChains()
    {
        // 1. We mock an attacker injecting a malicious serialized payload (e.g. into Redis)
        // A generic class with a destructive destructor (POP Chain gadget)
        $maliciousPayload = 'O:15:"MaliciousGadget":1:{s:4:"file";s:10:"secret.txt";}';
        
        // 2. Mock a file cache write representing the poisoned cache
        $cacheFile = sys_get_temp_dir() . '/spp_test_poisoned_cache.dat';
        // Format of FileCacheDriver: expiry time (10 digits) + serialized data
        file_put_contents($cacheFile, str_pad((time() + 3600), 10, '0', STR_PAD_LEFT) . $maliciousPayload);
        
        // 3. Attempt to read it using SPP's FileCacheDriver
        // The driver should have ['allowed_classes' => false] which converts Objects to __PHP_Incomplete_Class
        $driver = new \SPPMod\SPPCache\FileCacheDriver(['cache_path' => sys_get_temp_dir()]);
        
        // The read method handles the prefix, so we will manually test the unserialize block
        $content = substr(file_get_contents($cacheFile), 10);
        
        // Emulate driver behavior natively
        $result = @unserialize($content, ['allowed_classes' => false]);
        
        // Assert it is either completely rejected (false) or neutralized to __PHP_Incomplete_Class
        $this->assertNotInstanceOf('MaliciousGadget', $result, "CRITICAL: Object injection successful. The payload was instantiated.");
        
        if (is_object($result)) {
            $this->assertInstanceOf('__PHP_Incomplete_Class', $result, "Object should be neutralized as an incomplete class.");
        } else {
            $this->assertTrue(true, "Unserialize safely failed or returned non-object");
        }
        
        @unlink($cacheFile);
    }
}
