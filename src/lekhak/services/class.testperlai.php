<?php

namespace App\Lekhak\Services;

/**
 * Class TestPerlAi
 * Polyglot Service Proxy to perl runtime.
 */
class TestPerlAi extends \SPP\PolyglotProxy
{
    protected string $polyglotLang = 'perl';
    protected string $polyglotModule = 'C:/projects/apache/school1/src/lekhak/services/perl/service.testperlai.pl';
}
