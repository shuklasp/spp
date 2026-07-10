<div class="glass-panel">
    <h2>Attribute Routing & Middleware</h2>
    <p>SPP leverages PHP 8 attributes to elegantly map endpoints, attach middleware, and assign cache controls natively. No complex YAML maps are strictly necessary.</p>

    <div style="background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
        <h3 style="margin-top: 0;">Example Controller</h3>
        <pre><code>namespace App\Samvaad\Serv;

use SPPMod\SPPView\Attributes\Route;
use SPPMod\SPPView\Attributes\Middleware;

#[Route('/backend-showcase')]
class BackendShowcaseController extends \SPPMod\SPPView\ViewController
{
    #[Route('/partial/{name}', method: 'GET', name: 'showcase.partial')]
    #[Middleware(\App\Samvaad\Middleware\DemoMiddleware::class)]
    public function loadPartial(string $name)
    {
        // Smart content negotiation & attribute resolution
    }
}</code></pre>
    </div>

    <div style="display: flex; gap: 1rem;">
        <button class="btn btn-outline" hx-get="<?= \SPP\App::url('backend-showcase/routing/demo-auth', 'samvaad') ?>" hx-target="#routing-results">
            Test Authenticated Route
        </button>
        <button class="btn btn-outline" hx-post="<?= \SPP\App::url('backend-showcase/routing/demo-post', 'samvaad') ?>" hx-target="#routing-results">
            Test POST Route
        </button>
    </div>

    <div id="routing-results" style="margin-top: 2rem;">
        <!-- Results will load here -->
    </div>
</div>
