<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Registration works.
     */
    public function test_user_can_register_as_student(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Student',
            'email' => 'jane@example.com',
            'password' => 'secretPassword123',
            'password_confirmation' => 'secretPassword123',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'Jane Student',
                        'email' => 'jane@example.com',
                        'role' => 'student',
                    ],
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'role', 'created_at'],
                    'token',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'role' => 'student',
        ]);
    }

    /**
     * 5. Passwords are securely hashed.
     */
    public function test_registration_hashes_password_securely(): void
    {
        $plainPassword = 'superSecretPassword456';

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Security Test',
            'email' => 'security@example.com',
            'password' => $plainPassword,
            'password_confirmation' => $plainPassword,
        ])->assertStatus(201);

        $user = User::where('email', 'security@example.com')->first();
        $this->assertNotNull($user);

        // Plain password must NOT be stored
        $this->assertNotEquals($plainPassword, $user->password);

        // Password must be a valid Bcrypt hash verifiable via Hash facade
        $this->assertTrue(Hash::check($plainPassword, $user->password));

        // Password must not be serialized into JSON responses
        $userArray = $user->toArray();
        $this->assertArrayNotHasKey('password', $userArray);
    }

    /**
     * 6. Validation errors return appropriate 422 JSON responses.
     */
    public function test_registration_validation_errors_return_422_json(): void
    {
        // 1. Missing required fields
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['name', 'email', 'password'],
            ]);

        // 2. Invalid email format & short password mismatch
        $response2 = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test',
            'email' => 'not-a-valid-email',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ]);

        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    /**
     * 6. Duplicate email validation.
     */
    public function test_user_cannot_register_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Another John',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * 7. Students cannot assign themselves privileged roles during registration.
     */
    public function test_student_cannot_assign_themselves_admin_role_during_registration(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.role', 'student');

        $this->assertDatabaseHas('users', [
            'email' => 'attacker@example.com',
            'role' => 'student',
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'attacker@example.com',
            'role' => 'admin',
        ]);
    }

    /**
     * 2. Login works with valid credentials.
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'student@test.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'student@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'email' => 'student@test.com',
                    ],
                ],
            ])
            ->assertJsonStructure([
                'data' => ['user', 'token'],
            ]);
    }

    /**
     * 2. Login fails with invalid credentials.
     */
    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'student@test.com',
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'student@test.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * 3. Logout works and revokes current access token.
     */
    public function test_user_can_logout_and_revoke_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * 4 & 8. Protected endpoints reject unauthenticated requests with 401 JSON.
     */
    public function test_protected_endpoints_reject_unauthenticated_requests(): void
    {
        // 1. GET /api/v1/auth/user
        $response1 = $this->getJson('/api/v1/auth/user');
        $response1->assertStatus(401);

        // 2. GET /api/v1/profile
        $response2 = $this->getJson('/api/v1/profile');
        $response2->assertStatus(401);

        // 3. GET /api/v1/dashboard
        $response3 = $this->getJson('/api/v1/dashboard');
        $response3->assertStatus(401);

        // 4. Request without explicit Accept: application/json header must also return 401
        $response4 = $this->get('/api/v1/profile');
        $response4->assertStatus(401);
    }

    /**
     * 7. Students cannot assign themselves privileged roles via profile update.
     */
    public function test_student_cannot_assign_themselves_admin_role_via_profile_update(): void
    {
        $user = User::factory()->create(['name' => 'Original Name', 'role' => 'student']);
        $token = $user->createToken('test-token')->plainTextToken;

        // View profile
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Original Name')
            ->assertJsonPath('data.role', 'student');

        // Update profile while attempting to pass 'role' => 'admin'
        $updateResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/profile', [
                'name' => 'Updated Name',
                'role' => 'admin',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.role', 'student');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'role' => 'student',
        ]);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
            'role' => 'admin',
        ]);
    }
}
