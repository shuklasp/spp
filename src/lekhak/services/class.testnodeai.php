<?php

namespace App\Lekhak\Services;

/**
 * Class TestNodeAi
 * Polyglot Service Proxy to node runtime.
 */
class TestNodeAi extends \SPP\PolyglotProxy
{
    protected string $polyglotLang = 'node';
    protected string $polyglotModule = 'C:/projects/apache/school1/src/lekhak/services/node/service.testnodeai.js';
}
