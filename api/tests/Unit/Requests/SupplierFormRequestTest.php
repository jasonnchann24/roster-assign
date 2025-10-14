<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\SupplierFormRequest;
use Tests\TestCase;

class SupplierFormRequestTest extends TestCase
{
    public function test_it_has_expected_validation_rules()
    {
        $request = new SupplierFormRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);

        $this->assertContains('required', $rules['name']);
        $this->assertContains('required', $rules['email']);
        $this->assertContains('required', $rules['password']);

        $this->assertContains('unique:suppliers,email', $rules['email']);

        $this->assertContains('confirmed', $rules['password']);
    }

    public function test_it_allows_all_requests_by_default()
    {
        $request = new SupplierFormRequest();

        $this->assertTrue($request->authorize());
    }
}
