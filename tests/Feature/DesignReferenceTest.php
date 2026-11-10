<?php

namespace Tests\Feature;

use Tests\TestCase;

class DesignReferenceTest extends TestCase
{
    public function test_the_design_reference_page_is_available_while_developing()
    {
        $this->get('/design')->assertOk();
    }
}
