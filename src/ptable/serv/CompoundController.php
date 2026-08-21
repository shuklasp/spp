<?php
namespace App\ptable\Serv;

use SPPMod\SPPView\ViewController;

class CompoundController extends ViewController
{
    /**
     * Shows compounds for a specific element
     */
    public function explore($symbol)
    {
        $symbol = strtoupper(trim($symbol));
        
        // Get element details
        $element = PeriodicTableData::getElementBySymbol($symbol);
        
        if (!$element) {
            header("HTTP/1.0 404 Not Found");
            echo "Element not found.";
            return;
        }

        // Load all compounds
        $compoundsFile = dirname(__DIR__) . '/data/compounds.json';
        $allCompounds = [];
        if (file_exists($compoundsFile)) {
            $allCompounds = json_decode(file_get_contents($compoundsFile), true);
        }

        // Filter compounds containing the requested element
        $elementCompounds = array_filter($allCompounds, function($c) use ($symbol) {
            return in_array($symbol, $c['elements']);
        });

        // Use the native $this->render from SPPMod\SPPView\ViewController
        return $this->render('compounds', [
            'element' => $element,
            'compounds' => $elementCompounds,
            'title' => 'Compounds of ' . $element['name']
        ]);
    }

    /**
     * Shows the global explorer for all compounds
     */
    public function index()
    {
        $compoundsFile = dirname(__DIR__) . '/data/compounds.json';
        $allCompounds = [];
        if (file_exists($compoundsFile)) {
            $allCompounds = json_decode(file_get_contents($compoundsFile), true);
        }

        // 1. Get filters
        $search = strtolower($_GET['search'] ?? '');
        $types = isset($_GET['type']) ? (array)$_GET['type'] : [];
        $states = isset($_GET['state']) ? (array)$_GET['state'] : [];
        $elements = isset($_GET['elements']) ? (array)$_GET['elements'] : [];
        
        // 2. Filter logic
        $filtered = array_filter($allCompounds, function($c) use ($search, $types, $states, $elements) {
            if ($search) {
                $nameMatch = str_contains(strtolower($c['name']), $search);
                $formulaMatch = str_contains(strtolower($c['formula']), $search);
                if (!$nameMatch && !$formulaMatch) return false;
            }
            if (!empty($types)) {
                $ctype = $c['organic'] ? 'organic' : 'inorganic';
                if (!in_array($ctype, $types)) return false;
            }
            if (!empty($states)) {
                if (!in_array(strtolower($c['state']), $states)) return false;
            }
            if (!empty($elements)) {
                foreach ($elements as $el) {
                    if (!in_array($el, $c['elements'])) return false;
                }
            }
            return true;
        });

        // 3. Pagination
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = 24; // 24 is a nice multiple for grids
        $total = count($filtered);
        
        // 4. Slice array
        $paginated = array_slice($filtered, ($page - 1) * $perPage, $perPage);
        $hasMore = ($page * $perPage) < $total;

        $viewData = [
            'title' => 'Compounds Database',
            'compounds' => $paginated,
            'page' => $page,
            'hasMore' => $hasMore,
            'total' => $total
        ];

        // 5. Smart Content Negotiation for HTMX
        $isHtmx = isset($_SERVER['HTTP_HX_REQUEST']);
        
        if ($isHtmx) {
            // If appending the next page
            if (isset($_GET['append'])) {
                return $this->renderPartial('partials.compound_cards', $viewData);
            }
            // If just updating the grid (filtering)
            return $this->renderPartial('partials.compounds_grid', $viewData);
        }

        return $this->render('compounds_index', $viewData);
    }

    /**
     * Shows details for a single compound
     */
    public function show($id)
    {
        $compoundsFile = dirname(__DIR__) . '/data/compounds.json';
        $allCompounds = [];
        if (file_exists($compoundsFile)) {
            $allCompounds = json_decode(file_get_contents($compoundsFile), true);
        }

        $compound = null;
        foreach ($allCompounds as $c) {
            if ($c['id'] === $id) {
                $compound = $c;
                break;
            }
        }

        if (!$compound) {
            header("HTTP/1.0 404 Not Found");
            echo "Compound not found.";
            return;
        }

        return $this->render('compound_detail', [
            'title' => $compound['name'],
            'compound' => $compound
        ]);
    }
}
