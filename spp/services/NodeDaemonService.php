<?php
namespace SPP\Services;
use SPP\PolyglotProxy;

class NodeDaemonService extends PolyglotProxy {
    protected string $polyglotLang = 'node';
    protected string $polyglotModule = 'services/node/daemon_service.js';

    public function generate(string $prompt) {
        return $this->callPolyglot('generate', [$prompt], true);
    }
}
