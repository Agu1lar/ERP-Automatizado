<?php

namespace App\Support;

use App\Models\Domain\Fleet\EquipmentCategory;

class EquipmentCategoryResolver
{
    /** @var array<string, list<string>> */
    private const ALIASES = [
        'betoneira' => ['betoneira', 'betoneiras'],
        'martelete' => ['martelete', 'marteletes'],
        'escavadeira' => ['mini escavadeira', 'escavadeiras', 'escavadeira'],
        'compactador' => ['compactador', 'compactadores', 'placa vibratória', 'placa vibratoria'],
        'gerador' => ['gerador', 'geradores'],
        'bomba' => ['bomba', 'bombas'],
    ];

    /** Termo de equipamento mencionado no texto (categorias cadastradas + aliases). */
    public static function detectTermFromText(string $text): ?string
    {
        $lower = mb_strtolower($text);

        $categories = EquipmentCategory::query()
            ->where('ativo', true)
            ->orderByDesc('nome')
            ->pluck('nome');

        foreach ($categories as $name) {
            $slug = mb_strtolower((string) $name);

            if ($slug !== '' && str_contains($lower, $slug)) {
                return $slug;
            }
        }

        $needles = [];
        foreach (self::ALIASES as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                $needles[] = ['canonical' => $canonical, 'alias' => $alias];
            }
        }

        usort($needles, fn ($a, $b) => mb_strlen($b['alias']) <=> mb_strlen($a['alias']));

        foreach ($needles as $entry) {
            if (str_contains($lower, $entry['alias'])) {
                return $entry['canonical'];
            }
        }

        return null;
    }

    public static function resolveFromText(string $text): ?EquipmentCategory
    {
        $lower = mb_strtolower($text);

        $categories = EquipmentCategory::query()
            ->where('ativo', true)
            ->orderByDesc('nome')
            ->get();

        foreach ($categories as $category) {
            $name = mb_strtolower($category->nome);

            if ($name !== '' && str_contains($lower, $name)) {
                return $category;
            }
        }

        $needles = [];
        foreach (self::ALIASES as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                $needles[] = ['canonical' => $canonical, 'alias' => $alias];
            }
        }

        usort($needles, fn ($a, $b) => mb_strlen($b['alias']) <=> mb_strlen($a['alias']));

        foreach ($needles as $entry) {
            if (! str_contains($lower, $entry['alias'])) {
                continue;
            }

            $match = $categories->first(function (EquipmentCategory $category) use ($entry) {
                $name = mb_strtolower($category->nome);

                return str_contains($name, $entry['canonical']) || str_contains($name, $entry['alias']);
            });

            if ($match) {
                return $match;
            }
        }

        return null;
    }

    public static function labelForTerm(?string $term): ?string
    {
        if ($term === null || $term === '') {
            return null;
        }

        return mb_convert_case($term, MB_CASE_TITLE, 'UTF-8');
    }
}
