<?php

namespace Tests;

use App\Models\Domain\Organization\OperatingCompany;
use App\Support\ActiveOperatingCompany;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('operating_companies')) {
            return;
        }

        if (OperatingCompany::query()->exists()) {
            $defaultId = OperatingCompany::query()->where('slug', 'acesso')->value('id')
                ?? OperatingCompany::query()->orderBy('id')->value('id');

            if ($defaultId) {
                ActiveOperatingCompany::set((int) $defaultId);
            }
        }
    }
}
