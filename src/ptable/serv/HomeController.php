<?php
namespace App\ptable\Serv;

/**
 * ============================================================================
 * HomeController — Blade View Rendering
 * ============================================================================
 *
 * HOW THIS WORKS:
 * Controllers are referenced in pages.yml as:
 *   home:
 *     controller: \App\ptable\Serv\HomeController@index
 *
 * When SPPRouter matches the route, ViewRouter calls this method.
 * The method should return rendered HTML (string).
 *
 * RENDERING BLADE VIEWS:
 * SPPBlade looks for templates in: src/ptable/resources/views/
 * Template names use dot notation: 'home' → home.blade.php
 * Layouts use @extends('layouts.app') → layouts/app.blade.php
 *
 * SPP BLADE DIRECTIVES:
 *   @sppux('component', ['prop' => 'val'])  — Mount SPP-UX component
 *   @sppform('formName')                     — Render YAML form
 *   @sppauth ... @endsppauth                — Show only if logged in
 *   @sppguest ... @endsppguest              — Show only if NOT logged in
 *   @sppbind($entity)                       — Bind entity to form
 *   @sppoffline('key') ... @endsppoffline   — Offline cache template
 *
 * HOW TO ADD A NEW PAGE:
 *   1. Create resources/views/mypage.blade.php
 *   2. Add method: public function mypage() { return $this->render('mypage'); }
 *   3. Add route in pages.yml: mypage: { controller: \App\ptable\Serv\HomeController@mypage }
 *   2. Add method: public function mypage() { return $this->render('mypage'); }
 *   3. Add route in pages.yml: mypage: { controller: \App\ptable\Serv\HomeController@mypage }
 * ============================================================================
 */
use SPPMod\SPPView\ViewController;

class HomeController extends ViewController
{
    public function index()
    {
        if (class_exists('\SPPMod\Drishyam\SPPUX')) {
            \SPPMod\Drishyam\SPPUX::boot();
        }

        $elements = PeriodicTableData::getElements();
        foreach ($elements as &$el) {
            $wikiData = LocalDataService::getElementData($el['symbol']);
            if ($wikiData) {
                $el['phase'] = $wikiData['phase'] ?? null;
                if (!empty($wikiData['electron_configuration'])) {
                    $el['electron_configuration'] = LocalDataService::formatElectronConfig($wikiData['electron_configuration']);
                }
            }
        }
        unset($el); // remove reference

        return $this->render('home', [
            'title' => 'Periodic Table',
            'elements' => $elements
        ]);
    }

    public function element($symbol = null, $request = null)
    {
        if (empty($symbol)) {
            return $this->index();
        }

        $element = PeriodicTableData::getElementBySymbol($symbol);
        if (!$element) {
            return $this->index();
        }

        $wikiData = LocalDataService::getElementData($symbol) ?? [];

        $isHtmx = isset($_SERVER['HTTP_HX_REQUEST']);
        if ($isHtmx) {
            ob_start();
            extract(['element' => $element, 'wiki' => $wikiData]);
            include __DIR__ . '/../pages/partials/element_details.php';
            return ob_get_clean();
        }

        return $this->render('element', [
            'title' => $element['name'] . ' (' . $element['symbol'] . ')',
            'element' => $element,
            'wiki' => $wikiData
        ]);
    }
    
    public function study($symbol = null, $request = null)
    {
        if (empty($symbol)) {
            return $this->index();
        }

        $element = PeriodicTableData::getElementBySymbol($symbol);
        if (!$element) {
            return $this->index();
        }

        $wikiData = LocalDataService::getElementData($symbol) ?? [];

        return $this->render('study', [
            'title' => 'Study ' . $element['name'] . ' (' . $element['symbol'] . ')',
            'element' => $element,
            'wiki' => $wikiData
        ]);
    }

    public function about()
    {
        return $this->render('about', [
            'title' => 'About Periodic Table'
        ]);
    }

    /**
     * Guide page — renders the comprehensive Blade mode tutorial.
     * Route: guide => HomeController@guide (in pages.yml)
     */
    public function guide()
    {
        return $this->render('guide', [
            'title' => 'ptable Developer Guide',
        ]);
    }
}