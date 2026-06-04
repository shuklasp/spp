<?php
namespace SPP\Services;

use SPP\PolyglotProxy;

class PythonDaemonService extends PolyglotProxy
{
    protected string $polyglotLang = 'python';
    protected string $polyglotModule = 'services/python/daemon_service.py';
    protected bool $daemon = true;
}
