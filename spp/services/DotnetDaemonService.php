<?php
namespace SPP\Services;
use SPP\PolyglotProxy;

class DotnetDaemonService extends PolyglotProxy {
    protected string $polyglotLang = 'dotnet';
    protected string $polyglotModule = 'services/dotnet/dotnet.csproj';

    public function generate(string $prompt) {
        return $this->callPolyglot('generate', [$prompt], true);
    }
}
