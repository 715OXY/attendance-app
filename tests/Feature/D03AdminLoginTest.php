<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class D03AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function 異常系_メールアドレスが未入力の場合はバリデーションエラーになる(): void
    {
        // Arrange
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'admin_status' => true,
        ]);

        $data = [
            'email' => '',
            'password' => 'password',
        ];

        // Act
        $response = $this->post(route('admin.login.store'), $data);

        // Assert
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);

        $this->assertGuest();
    }

    /**
     * @test
     */
    public function 異常系_パスワードが未入力の場合はバリデーションエラーになる(): void
    {
        // Arrange
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'admin_status' => true,
        ]);

        $data = [
            'email' => 'admin@example.com',
            'password' => '',
        ];

        // Act
        $response = $this->post(route('admin.login.store'), $data);

        // Assert
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);

        $this->assertGuest();
    }

    /**
     * @test
     */
    public function 異常系_登録内容と一致しない場合はログインできない(): void
    {
        // Arrange
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'admin_status' => true,
        ]);

        $data = [
            'email' => 'wrong-admin@example.com',
            'password' => 'password',
        ];

        // Act
        $response = $this->post(route('admin.login.store'), $data);

        // Assert
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);

        $this->assertGuest();
    }
}
