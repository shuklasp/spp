<?php
namespace SPPMod\SPPLang;

interface TranslationRepositoryInterface {
    public function ensureSchema(): void;
    public function save(string $key, string $locale, string $translation, string $status = 'active'): void;
    public function getMany(array $filters = []): array;
    public function getOne(string $key, string $locale = 'en'): string;
}
