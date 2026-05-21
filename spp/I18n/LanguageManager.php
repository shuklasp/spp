<?php
namespace SPP\I18n;

/**
 * Simple language manager used by the core to provide multilingual support.
 * It works with a `language` table (id, code, name, is_default) that is
 * created by the framework migration scripts.
 */
class LanguageManager
{
    /** @var \PDO */
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Return all languages defined in the system.
     *
     * @return array<int, array{code:string,name:string,default:bool}>
     */
    public function getLanguages(): array
    {
        $stmt = $this->pdo->query('SELECT code, name, is_default FROM language');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get the currently selected language code (from session or default).
     */
    public function getCurrentLanguage(): string
    {
        if (isset($_SESSION['spp_lang'])) {
            return $_SESSION['spp_lang'];
        }
        $stmt = $this->pdo->query('SELECT code FROM language WHERE is_default = 1 LIMIT 1');
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row['code'] ?? 'en';
    }

    /**
     * Switch the language for the current request.
     */
    public function setLanguage(string $code): void
    {
        // simple validation – ensure the code exists
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM language WHERE code = :code');
        $stmt->execute(['code' => $code]);
        if ((int) $stmt->fetchColumn() > 0) {
            $_SESSION['spp_lang'] = $code;
        }
    }
}
