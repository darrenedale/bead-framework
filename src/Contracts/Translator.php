<?php

namespace Equit\Contracts;

interface Translator
{
    public function language(): string;
    public function setLanguage(): string;
    public function hasTranslation(string $string): bool;
    public function translate(string $string): string;
}
