<?php
namespace App\Lekhak\UI;

use SPP\I18n\LanguageManager;

/**
 * LanguageSwitcher
 *
 * Renders a language switcher widget for the front-end.
 * Uses the core LanguageManager to fetch available languages
 * and the current selection.
 */
class LanguageSwitcher
{
    private LanguageManager $langManager;

    public function __construct(LanguageManager $langManager)
    {
        $this->langManager = $langManager;
    }

    /**
     * Render the language switcher as an HTML dropdown.
     *
     * @param string $currentUrl  The current page URL (used to append ?lang= param)
     * @return string HTML markup
     */
    public function render(string $currentUrl = ''): string
    {
        $languages = $this->langManager->getLanguages();
        $current   = $this->langManager->getCurrentLanguage();

        if (count($languages) <= 1) {
            return ''; // No need for a switcher with only one language
        }

        $baseUrl = $currentUrl ?: ($_SERVER['REQUEST_URI'] ?? '/');
        // Strip existing lang= parameter
        $baseUrl = preg_replace('/[?&]lang=[^&]*/', '', $baseUrl);
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        $html = '<div class="spp-language-switcher" id="spp-lang-switcher">';
        $html .= '<select onchange="window.location.href=this.value" aria-label="Language">';

        foreach ($languages as $lang) {
            $code     = $lang['code'];
            $name     = $lang['name'];
            $selected = ($code === $current) ? ' selected' : '';
            $url      = $baseUrl . $separator . 'lang=' . urlencode($code);
            $html    .= "<option value=\"{$url}\"{$selected}>{$name}</option>";
        }

        $html .= '</select>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render the switcher as a horizontal list of links (for navbars).
     */
    public function renderLinks(string $currentUrl = ''): string
    {
        $languages = $this->langManager->getLanguages();
        $current   = $this->langManager->getCurrentLanguage();

        if (count($languages) <= 1) return '';

        $baseUrl = $currentUrl ?: ($_SERVER['REQUEST_URI'] ?? '/');
        $baseUrl = preg_replace('/[?&]lang=[^&]*/', '', $baseUrl);
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        $html = '<nav class="spp-language-links" aria-label="Language Navigation">';
        $items = [];

        foreach ($languages as $lang) {
            $code = $lang['code'];
            $name = $lang['name'];
            $url  = $baseUrl . $separator . 'lang=' . urlencode($code);
            $cls  = ($code === $current) ? 'spp-lang-active' : '';
            $items[] = "<a href=\"{$url}\" class=\"spp-lang-link {$cls}\" hreflang=\"{$code}\">{$name}</a>";
        }

        $html .= implode(' | ', $items);
        $html .= '</nav>';

        return $html;
    }

    /**
     * Process an incoming language switch request.
     * Call this early in the request lifecycle.
     */
    public static function handleRequest(LanguageManager $langManager): void
    {
        if (isset($_GET['lang'])) {
            $langManager->setLanguage($_GET['lang']);
        }
    }
}
