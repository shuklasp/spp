<?php
namespace SPP\Services;
use SPP\PolyglotProxy;

class CppDaemonService extends PolyglotProxy {
    protected string $polyglotLang = 'compiler';
    protected string $polyglotModule = 'services/cpp/daemon_service.cpp';

    public function generate(string $prompt) {
        return $this->callPolyglot('generate', [$prompt], true);
    }
}
