<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SupportsD14StaffTests;
use Tests\TestCase;

class D14AdminStaffTest extends TestCase
{
    use RefreshDatabase, SupportsD14StaffTests;

    /**
     * @test
     */
    public function 正常系_スタッフ一覧に全一般ユーザーの氏名とメールアドレスが表示される(): void
    {
        // Arrange
        $admin = User::factory()->create([
            'name' => '管理者ユーザー',
            'email' => 'admin@example.com',
            'admin_status' => true,
        ]);

        User::factory()->create([
            'name' => 'テスト太郎',
            'email' => 'taro@example.com',
            'admin_status' => false,
        ]);

        User::factory()->create([
            'name' => 'テスト花子',
            'email' => 'hanako@example.com',
            'admin_status' => false,
        ]);

        // Act
        $response = $this
            ->actingAs($admin)
            ->get(route('admin.staff.index'));

        // Assert
        $response->assertOk();

        $response->assertSee('テスト太郎');
        $response->assertSee('taro@example.com');

        $response->assertSee('テスト花子');
        $response->assertSee('hanako@example.com');

        // 管理者自身はスタッフ一覧に含めない
        $response->assertDontSee('管理者ユーザー');
        $response->assertDontSee('admin@example.com');
    }
}
