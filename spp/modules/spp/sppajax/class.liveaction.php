<?php

namespace SPPMod\SPPAjax;

/**
 * class LiveAction
 * 
 * Builder for unified service responses that instruct the frontend to perform
 * specific DOM manipulations, redirects, or notifications.
 */
class LiveAction
{
    private array $instructions = [];
    private array $data = [];
    private string $status = 'ok';

    public function __construct(array $initialData = [])
    {
        $this->data = $initialData;
    }

    /**
     * Set the response status (e.g. 'ok', 'error', 'redirect').
     */
    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Set the general data payload (e.g. results from a GraphQL query).
     */
    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Get current data payload.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get current instructions list.
     */
    public function getInstructions(): array
    {
        return $this->instructions;
    }

    /**
     * Replace the inner HTML of an element matching the selector.
     */
    public function replace(string $selector, string $html): self
    {
        $this->instructions[] = ['action' => 'replace', 'selector' => $selector, 'html' => $html];
        return $this;
    }

    /**
     * Morph the element matching the selector with new HTML.
     * Unlike replace(), morph() attempts to preserve focus and attributes.
     */
    public function morph(string $selector, string $html): self
    {
        $this->instructions[] = ['action' => 'morph', 'selector' => $selector, 'html' => $html];
        return $this;
    }

    /**
     * Update an element's attribute.
     */
    public function attr(string $selector, string $attr, string $value): self
    {
        $this->instructions[] = ['action' => 'attr', 'selector' => $selector, 'attr' => $attr, 'value' => $value];
        return $this;
    }

    /**
     * Append HTML to an element.
     */
    public function append(string $selector, string $html): self
    {
        $this->instructions[] = ['action' => 'append', 'selector' => $selector, 'html' => $html];
        return $this;
    }

    /**
     * Prepend HTML to an element.
     */
    public function prepend(string $selector, string $html): self
    {
        $this->instructions[] = ['action' => 'prepend', 'selector' => $selector, 'html' => $html];
        return $this;
    }

    /**
     * Remove an element from the DOM.
     */
    public function remove(string $selector): self
    {
        $this->instructions[] = ['action' => 'remove', 'selector' => $selector];
        return $this;
    }

    /**
     * Instruct the frontend to redirect to a new URL.
     */
    public function redirect(string $url): self
    {
        $this->instructions[] = ['action' => 'redirect', 'url' => $url];
        return $this;
    }

    /**
     * Show a notification (toast).
     */
    public function notify(string $message, string $type = 'info'): self
    {
        $this->instructions[] = ['action' => 'notify', 'message' => $message, 'type' => $type];
        return $this;
    }

    /**
     * Update the client-side global SPPStore (spp_root_store).
     */
    public function syncStore(array $state): self
    {
        $this->instructions[] = ['action' => 'store', 'detail' => $state];
        return $this;
    }

    /**
     * Render a template/partial and use it in a replace/morph instruction.
     * 
     * @param string $template Path to template relative to app src/pages or src/partials
     * @param array $data Data to extract into the template
     * @param string $selector DOM selector to target
     * @param string $action 'replace' or 'morph'
     */
    public function render(string $template, array $data = [], string $selector = '', string $action = 'replace'): self
    {
        ob_start();
        extract($data);
        
        $src = \SPPMod\SPPView\Pages::getDefault('pagedir') ?: '/src/pages';
        $fullPath = SPP_APP_DIR . $src . '/' . ltrim($template, '/');
        
        if (!file_exists($fullPath)) {
            // Check in src/partials fallback
            $fullPath = SPP_APP_DIR . '/src/partials/' . ltrim($template, '/');
        }

        if (file_exists($fullPath)) {
            include $fullPath;
        } else {
            echo "<!-- Partial not found: {$template} -->";
        }
        
        $html = ob_get_clean();
        
        if ($selector) {
            if ($action === 'morph') $this->morph($selector, $html);
            else $this->replace($selector, $html);
        } else {
            // If no selector, just set it as the primary data payload
            $this->data['html'] = $html;
        }

        return $this;
    }

    /**
     * Instruct the frontend to open a modal.
     */
    public function modal(string $title, string $html, array $actions = []): self
    {
        $this->instructions[] = ['action' => 'modal', 'title' => $title, 'html' => $html, 'actions' => $actions];
        return $this;
    }

    /**
     * Instruct the frontend to close the active modal.
     */
    public function closeModal(): self
    {
        $this->instructions[] = ['action' => 'closeModal'];
        return $this;
    }

    /**
     * Instruct the frontend to refresh the current view.
     */
    public function refresh(): self
    {
        $this->instructions[] = ['action' => 'refresh'];
        return $this;
    }

    /**
     * Trigger a custom JS event on the window or a specific element.
     */
    public function dispatch(string $event, array $detail = [], ?string $selector = null): self
    {
        $this->instructions[] = ['action' => 'dispatch', 'event' => $event, 'detail' => $detail, 'selector' => $selector];
        return $this;
    }

    /**
     * Execute arbitrary JavaScript on the client.
     */
    public function script(string $js): self
    {
        $this->instructions[] = ['action' => 'script', 'html' => $js];
        return $this;
    }

    /**
     * Show a native browser alert.
     */
    public function alert(string $message): self
    {
        $this->instructions[] = ['action' => 'alert', 'message' => $message];
        return $this;
    }

    /**
     * Assign a value to an element's property (e.g. innerHTML, value, checked).
     */
    public function assign(string $selector, string $prop, string $value): self
    {
        $this->instructions[] = ['action' => 'assign', 'selector' => $selector, 'prop' => $prop, 'value' => $value];
        return $this;
    }

    /**
     * Call a global JavaScript function with arguments.
     */
    public function call(string $func, ...$args): self
    {
        $this->instructions[] = ['action' => 'call', 'func' => $func, 'args' => $args];
        return $this;
    }

    /**
     * Clear the content or a specific attribute of an element.
     */
    public function clear(string $selector, string $attr = 'innerHTML'): self
    {
        $this->instructions[] = ['action' => 'clear', 'selector' => $selector, 'attr' => $attr];
        return $this;
    }

    /**
     * Execute the response via SPPAjax.
     */
    public function send(): never
    {
        // Use fully qualified name to avoid ambiguity with the namespace name
        \SPPMod\SPPAjax\SPPAjax::respond($this->status, [
            'data' => $this->data,
            'instructions' => $this->instructions
        ]);
    }

    /**
     * Static helper for GraphQL integration.
     */
    public static function query(string $query, array $variables = []): self
    {
        if (!\SPP\Module::isEnabled('sppinterdb')) {
            throw new \Exception("SPPInterDB module is required for LiveAction::query()");
        }
        
        $db = new \SPPMod\SPPInterDB\SPPInterDB();
        $res = $db->graphql($query, $variables);
        return new self($res);
    }

    /**
     * Static helper for SQL integration.
     */
    public static function sql(string $query, array $params = []): self
    {
        if (!\SPP\Module::isEnabled('sppdb')) {
            throw new \Exception("SPPDB module is required for LiveAction::sql()");
        }
        
        $db = new \SPPMod\SPPDB\SPPDB();
        $res = $db->execute_query($query, $params);
        return new self(['data' => $res]);
    }
}
