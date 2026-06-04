<?php

namespace App\Lekhak\Services;

/**
 * Class TestJavaAi
 * Polyglot Service Proxy to java runtime.
 */
class TestJavaAi extends \SPP\PolyglotProxy
{
    protected string $polyglotLang = 'java';
    protected string $polyglotModule = 'C:/projects/apache/school1/src/lekhak/services/java/ServiceTestJavaAi.java';
}
