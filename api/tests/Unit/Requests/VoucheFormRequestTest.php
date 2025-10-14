<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\VoucheFormRequest;
use App\Models\Supplier;
use App\Models\Vouche;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Tests\TestCase;

class VoucheFormRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_has_expected_validation_rules()
    {
        $user = Supplier::factory()->create();
        $this->actingAs($user);

        $request = new VoucheFormRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('vouched_for_id', $rules);
        $this->assertArrayHasKey('message', $rules);
        $this->assertArrayHasKey('vouched_by_id', $rules);

        $vouchedForIdRules = $rules['vouched_for_id'];
        $this->assertContains('required', $vouchedForIdRules);
        $this->assertContains('integer', $vouchedForIdRules);
        $this->assertContains('exists:suppliers,id', $vouchedForIdRules);
        $this->assertContains('different:vouched_by_id', $vouchedForIdRules);

        $hasNotInRule = false;
        foreach ($vouchedForIdRules as $rule) {
            if ($rule instanceof \Illuminate\Validation\Rules\NotIn) {
                $hasNotInRule = true;
                break;
            }
        }
        $this->assertTrue($hasNotInRule, 'Should have notIn rule to prevent self-vouching');

        $vouchedByIdRules = $rules['vouched_by_id'];
        $this->assertContains('required', $vouchedByIdRules);
        $this->assertContains('integer', $vouchedByIdRules);
        $this->assertContains('exists:suppliers,id', $vouchedByIdRules);

        $hasUniqueRule = false;
        foreach ($vouchedForIdRules as $rule) {
            if ($rule instanceof \Illuminate\Validation\Rules\Unique) {
                $hasUniqueRule = true;
                break;
            }
        }
        $this->assertTrue($hasUniqueRule, 'Should have unique rule to prevent duplicate vouches');

        $messageRules = $rules['message'];
        $this->assertContains('nullable', $messageRules);
        $this->assertContains('string', $messageRules);
        $this->assertContains('max:1000', $messageRules);
        $this->assertContains('min:10', $messageRules);
    }

    public function test_it_prepares_vouched_by_id_from_authenticated_user()
    {
        $user = Supplier::factory()->create();
        $this->actingAs($user);

        $request = new VoucheFormRequest();
        $request->merge(['vouched_for_id' => 2, 'message' => 'Test message']);

        // Force call the protected method using reflection
        $reflection = new \ReflectionClass($request);
        $method = $reflection->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertEquals($user->id, $request->input('vouched_by_id'));
    }

    public function test_it_has_custom_validation_messages()
    {
        $request = new VoucheFormRequest();
        $messages = $request->messages();

        // Test business-specific error messages
        $this->assertArrayHasKey('vouched_for_id.different', $messages);
        $this->assertArrayHasKey('vouched_for_id.not_in', $messages);
        $this->assertArrayHasKey('vouched_for_id.unique', $messages);

        // Test message content is business-appropriate
        $this->assertEquals('You cannot vouch for yourself.', $messages['vouched_for_id.different']);
        $this->assertEquals('You cannot vouch for yourself.', $messages['vouched_for_id.not_in']);
        $this->assertEquals('You have already vouched for this supplier.', $messages['vouched_for_id.unique']);
    }

    public function test_it_has_custom_attributes_for_better_ux()
    {
        $request = new VoucheFormRequest();
        $attributes = $request->attributes();

        // Test user-friendly field names
        $this->assertArrayHasKey('vouched_for_id', $attributes);
        $this->assertArrayHasKey('message', $attributes);

        $this->assertEquals('supplier', $attributes['vouched_for_id']);
        $this->assertEquals('vouch message', $attributes['message']);
    }

    public function test_it_authorizes_authenticated_users()
    {
        $user = Supplier::factory()->create();
        $supplier = Supplier::factory()->create();

        $this->actingAs($user);

        $request = new VoucheFormRequest();
        $request->merge(['vouched_for_id' => $supplier->id]);

        $this->assertTrue($request->authorize());
    }

    public function test_it_denies_unauthenticated_users()
    {
        $supplier = Supplier::factory()->create();

        $request = new VoucheFormRequest();
        $request->merge(['vouched_for_id' => $supplier->id]);

        $this->assertFalse($request->authorize());
    }

    public function test_it_validates_against_self_vouching()
    {
        $user = Supplier::factory()->create();
        $this->actingAs($user);

        $request = new VoucheFormRequest();
        $request->merge([
            'vouched_for_id' => $user->id,
            'vouched_by_id' => $user->id,
            'message' => 'Valid message here'
        ]);

        $this->assertTrue($request->authorize());

        $rules = $request->rules();
        $validator = Validator::make($request->all(), $rules, $request->messages(), $request->attributes());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('vouched_for_id', $validator->errors()->toArray());
        $this->assertEquals(
            'You cannot vouch for yourself.',
            $validator->errors()->first('vouched_for_id')
        );
    }

    public function test_it_should_fail_if_duplicate_vouch_attempted()
    {
        $user = Supplier::factory()->create();
        $supplier = Supplier::factory()->create();

        // Create existing vouch
        Vouche::factory()->create([
            'vouched_by_id' => $user->id,
            'vouched_for_id' => $supplier->id
        ]);

        $this->actingAs($user);

        $request = new VoucheFormRequest();
        $request->merge([
            'vouched_for_id' => $supplier->id,
            'vouched_by_id' => $user->id,
            'message' => 'Another valid vouch message'
        ]);

        $rules = $request->rules();
        $validator = Validator::make($request->all(), $rules, $request->messages(), $request->attributes());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('vouched_for_id', $validator->errors()->toArray());
        $this->assertEquals(
            'You have already vouched for this supplier.',
            $validator->errors()->first('vouched_for_id')
        );
    }

    public function test_it_allows_vouching_for_different_suppliers()
    {
        $user = Supplier::factory()->create();
        $supplier1 = Supplier::factory()->create();
        $supplier2 = Supplier::factory()->create();

        // Create existing vouch for supplier1
        Vouche::factory()->create([
            'vouched_by_id' => $user->id,
            'vouched_for_id' => $supplier1->id
        ]);

        $this->actingAs($user);

        // Should allow vouching for supplier2
        $request = new VoucheFormRequest();
        $request->merge([
            'vouched_for_id' => $supplier2->id,
            'vouched_by_id' => $user->id,
            'message' => 'Valid vouch message'
        ]);

        $this->assertTrue($request->authorize());
    }
}
