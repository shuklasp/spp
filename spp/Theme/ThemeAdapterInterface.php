<?php
namespace SPP\Theme;

interface ThemeAdapterInterface {
    /**
     * Load the raw template source (e.g., Twig, Blade, PHP) identified by a logical name.
     *
     * @param string $name Logical template name (e.g., 'page--home')
     * @return string Raw template content
     */
    public function loadTemplate(string $name): string;

    /**
     * Render a template with the provided context.
     *
     * @param string $template Logical template name
     * @param array  $context  Key/value pairs passed to the templating engine
     * @return string Rendered HTML output
     */
    public function render(string $template, array $context = []): string;
}
