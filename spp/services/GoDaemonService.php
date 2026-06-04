<?php
namespace SPP\Services;
use SPP\PolyglotProxy;

class GoDaemonService extends PolyglotProxy {
    protected string $polyglotLang = 'go';
    protected string $polyglotModule = 'services/go/daemon_service.go';

    public function generate(string $prompt) {
        return $this->callPolyglot('generate', [$prompt], true);
    }
}
