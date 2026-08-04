<?php

namespace Tests;

use App\Models\Company;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // `Company::current()` memoriza la fila en una propiedad estática, que
        // sobrevive entre tests del mismo proceso. Sin esto, un test heredaría
        // los datos del negocio de otro y las pruebas dejarían de ser aisladas.
        Company::forgetCurrent();
    }
}
