<?php

namespace Tests\Unit;

use App\Support\TextSearch;
use PHPUnit\Framework\TestCase;

class TextSearchTest extends TestCase
{
    public function test_matches_ignores_case_and_accents(): void
    {
        $this->assertTrue(TextSearch::matches('Martelete Bosch', 'martelete'));
        $this->assertTrue(TextSearch::matches('São Paulo', 'sao paulo'));
        $this->assertTrue(TextSearch::matches('PAT-DEMO-001', 'pat-demo'));
        $this->assertFalse(TextSearch::matches('Betoneira', 'gerador'));
    }
}
