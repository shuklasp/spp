<?php
namespace SPP\Services;
use SPP\PolyglotProxy;

class JavaDaemonService extends PolyglotProxy {
    protected string $polyglotLang = 'java';
    protected string $polyglotModule = 'services.java.DaemonService';

    public function generate(string $prompt) {
        return $this->callPolyglot('generate', [$prompt], true);
    }
}
