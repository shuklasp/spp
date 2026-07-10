<?php

namespace App\Lekhak\Views;

use SPPMod\SPPView\SPPView;
use SPPMod\Drishyam\Drishyam;
use SPPMod\Drishyam\TemplateMacros;

class Spp.php extends SPPView
{
    public function render(array $data = []): string
    {
        // Render Blade Fragment
        $drishyam = new Drishyam();
        $bladeContent = $drishyam->render('lekhak.fragments.spp.php_fragment', $data);
        $partialContent = TemplateMacros::spppartial('partials/spp.php_extra.html', $data);

        return $this->html([
            $this->head([
                $this->title('Mixed Paradigm - Kitchen Sink'),
                $this->script(['type' => 'module', 'src' => '/src/lekhak/comp/Spp.phpIsland.js']),
                $this->script(['src' => '/spp/admin/js/htmx.min.js']),
                $this->script(['src' => '/spp/admin/js/turbo-streams.min.js'])
            ]),
            $this->body([
                $this->div(['style' => 'font-family: sans-serif; max-width: 900px; margin: auto; padding: 2rem;'], [
                    $this->h1('Layer 1: SPPView (Native AST)'),
                    $this->p('This outer shell is purely PHP AST.'),
                    
                    $this->hr(),
                    
                    $this->h2('Layer 2: Drishyam (Blade)'),
                    $this->div([], [$bladeContent]),
                    
                    $this->hr(),
                    
                    $this->h2('Layer 3: SPPUX (Reactive Island)'),
                    $this->tag('spp-element', ['name' => 'Spp.phpIsland'], []),
                    
                    $this->hr(),
                    
                    $this->h2('Layer 4: External View Partial (@spppartial)'),
                    $this->div([], [$partialContent])
                ])
            ])
        ]);
    }
}