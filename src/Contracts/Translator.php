<?php

namespace Equit\Contracts;

interface Translator
{
    public function language(): string;
    public function setLanguage(string $language): void;
    public function hasTranslation(string $string): bool;
    public function translate(string $string): string;
}
