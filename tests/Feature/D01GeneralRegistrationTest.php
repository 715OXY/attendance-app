<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class D01GeneralRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function 異常系_名前が未入力の場合はバリデーションエラーになる(): void
    {
        // Arrange
        $data = [
            'name' => '',
            'email' => 'd01-name@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];

        // Act
        $response = $this->post(route('register.store'), $data);

        // Assert
        $response->assertSessionHasErrors([
            'name' => 'お名前を入力してください',
        ]);

        $this->assertGuest();

        $this->assertDatabaseMissing('users', [
            'email' => 'd01-name@example.com',
        ]);
    }

    /**
     * @test
     */
    public function 異常系_メールアドレスが未入力の場合はバリデーションエラーになる(): void
    {
        // Arrange
        $data = [
            'name' => 'テスト太郎',
            'email' => '',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];

        // Act
        $response = $this->post(route('register.store'), $data);

        // Assert
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);

        $this->assertGuest();
    }

    /**
     * @test
     */
    public function 境界値_パスワードが7文字の場合はバリデーションエラーになる(): void
    {
        // Arrange
        $data = [
            'name' => 'テスト太郎',
            'email' => 'd01-short-password@example.com',
            'password' => 'pass123',
            'password_confirmation' => 'pass123',
        ];

        // Act
        $response = $this->post(route('register.store'), $data);

        // Assert
        $response->assertSessionHasErrors([
            'password' => 'パスワードは8文字以上で入力してください',
        ]);

        $this->assertGuest();

        $this->assertDatabaseMissing('users', [
            'email' => 'd01-short-password@example.com',
        ]);
    }

    /**
     * @test
     */
    public function 異常系_確認用パスワードが一致しない場合はバリデーションエラーになる(): void
    {
        // Arrange
        $data = [
            'name' => 'テスト太郎',
            'email' => 'd01-confirmation@example.com',
            'password' => 'password',
            'password_confirmation' => 'password123',
        ];

        // Act
        $response = $this->post(route('register.store'), $data);

        // Assert
        $response->assertSessionHasErrors([
            'password' => 'パスワードと一致しません',
        ]);

        $this->assertGuest();

        $this->assertDatabaseMissing('users', [
            'email' => 'd01-confirmation@example.com',
        ]);
    }

    /**
     * @test
     */
    public function 異常系_パスワードが未入力の場合はバリデーションエラーになる(): void
    {
        // Arrange
        $data = [
            'name' => 'テスト太郎',
            'email' => 'd01-empty-password@example.com',
            'password' => '',
            'password_confirmation' => '',
        ];

        // Act
        $response = $this->post(route('register.store'), $data);

        // Assert
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);

        $this->assertGuest();

        $this->assertDatabaseMissing('users', [
            'email' => 'd01-empty-password@example.com',
        ]);
    }

    /**
     * @test
     */
    public function 正常系_正しい入力内容で一般ユーザーを登録できる(): void
    {
        // Arrange
        $data = [
            'name' => 'テスト太郎',
            'email' => 'd01-success@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];

        // Act
        $response = $this->post(route('register.store'), $data);

        // Assert
        $response->assertRedirect(route('verification.notice'));

        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'name' => 'テスト太郎',
            'email' => 'd01-success@example.com',
        ]);

        $user = User::where('email', $data['email'])->first();

        $this->assertNull($user->email_verified_at);
    }
}
