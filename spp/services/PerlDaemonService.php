<?php
namespace SPP\Services;
use SPP\PolyglotProxy;

class PerlDaemonService extends PolyglotProxy {
    protected string $polyglotLang = 'perl';
    protected string $polyglotModule = 'services/perl/daemon_service.pl';

    public function generate(string $prompt) {
        return $this->callPolyglot('generate', [$prompt], true);
    }
}
