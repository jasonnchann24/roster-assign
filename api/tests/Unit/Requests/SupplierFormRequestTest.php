<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\SupplierFormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SupplierFormRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_has_correct_validation_rules()
    {
        $request = new SupplierFormRequest();
        $rules = $request->rules();

        $expectedRules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:suppliers,email',
            'password' => 'required|string|min:6|confirmed',
        ];

        $this->assertEquals($expectedRules, $rules);
    }
}
