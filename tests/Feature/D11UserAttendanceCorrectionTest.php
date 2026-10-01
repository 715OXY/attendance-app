<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class D11UserAttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function 異常系_出勤時間が退勤時間より後の場合はエラーになる(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user, '2026-10-01');

        $data = [
            'new_date' => '10月01日',
            'new_clock_in' => '19:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['12:00'],
            'new_break_out' => ['13:00'],
            'comment' => '修正申請テスト',
        ];

        // Act
        $response = $this
            ->actingAs($user)
            ->post(
                route('attendance.correction.store', $attendance->id),
                $data
            );

        // Assert
        $response->assertSessionHasErrors([
            'new_clock_out' => '出勤時間もしくは退勤時間が不適切な値です',
        ]);

        $this->assertDatabaseCount('attendance_correction_requests', 0);
    }

    /**
     * @test
     */
    public function 異常系_休憩開始時間が退勤時間より後の場合はエラーになる(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user, '2026-10-01');

        $data = [
            'new_date' => '10月01日',
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['18:30'],
            'new_break_out' => ['18:45'],
            'comment' => '修正申請テスト',
        ];

        // Act
        $response = $this
            ->actingAs($user)
            ->post(
                route('attendance.correction.store', $attendance->id),
                $data
            );

        // Assert
        $response->assertSessionHasErrors([
            'new_break_in.0' => '休憩時間が不適切な値です',
        ]);

        $this->assertDatabaseCount('attendance_correction_requests', 0);
    }

    /**
     * @test
     */
    public function 異常系_休憩終了時間が退勤時間より後の場合はエラーになる(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user, '2026-10-01');

        $data = [
            'new_date' => '10月01日',
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['17:30'],
            'new_break_out' => ['18:30'],
            'comment' => '修正申請テスト',
        ];

        // Act
        $response = $this
            ->actingAs($user)
            ->post(
                route('attendance.correction.store', $attendance->id),
                $data
            );

        // Assert
        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);

        $this->assertDatabaseCount('attendance_correction_requests', 0);
    }

    /**
     * @test
     */
    public function 異常系_備考が未入力の場合はエラーになる(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user, '2026-10-01');

        $data = [
            'new_date' => '10月01日',
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['12:00'],
            'new_break_out' => ['13:00'],
            'comment' => '',
        ];

        // Act
        $response = $this
            ->actingAs($user)
            ->post(
                route('attendance.correction.store', $attendance->id),
                $data
            );

        // Assert
        $response->assertSessionHasErrors([
            'comment' => '備考を記入してください',
        ]);

        $this->assertDatabaseCount('attendance_correction_requests', 0);
    }

    /**
     * @test
     */
    public function 正常系_修正申請が実行され管理者の申請一覧と承認画面に表示される(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'テスト太郎',
            'admin_status' => false,
        ]);

        $admin = User::factory()->create([
            'name' => '管理者',
            'admin_status' => true,
        ]);

        $attendance = $this->createAttendance(
            $user,
            '2026-10-01',
            true
        );

        $data = [
            'new_date' => '10月01日',
            'new_clock_in' => '09:10',
            'new_clock_out' => '18:15',
            'new_break_in' => ['12:10'],
            'new_break_out' => ['13:10'],
            'comment' => '修正申請テスト',
        ];

        // Act：一般ユーザーが修正申請
        $response = $this
            ->actingAs($user)
            ->post(
                route('attendance.correction.store', $attendance->id),
                $data
            );

        // Assert：申請が保存される
        $response->assertRedirect(
            route('attendance.show', $attendance->id)
        );

        $this->assertDatabaseHas('attendance_correction_requests', [
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'requested_clock_in' => '2026-10-01 09:10:00',
            'requested_clock_out' => '2026-10-01 18:15:00',
            'requested_comment' => '修正申請テスト',
            'status' => 0,
        ]);

        $application = AttendanceCorrectionRequest::firstOrFail();

        $this->assertDatabaseHas('attendance_correction_breaks', [
            'attendance_correction_request_id' => $application->id,
            'requested_break_in' => '2026-10-01 12:10:00',
            'requested_break_out' => '2026-10-01 13:10:00',
        ]);

        // 承認前なので元の勤怠は変更されていない
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '2026-10-01 09:00:00',
            'clock_out' => '2026-10-01 18:00:00',
        ]);

        // Act：管理者で申請一覧を表示
        $response = $this
            ->actingAs($admin)
            ->get(route('attendance.application.list'));

        // Assert
        $response->assertOk();
        $response->assertSee('テスト太郎');
        $response->assertSee('修正申請テスト');

        // Act：管理者で承認画面を表示
        $response = $this->get(
            route('admin.application.show', [
                'attendance_correct_request_id' => $application->id,
            ])
        );

        // Assert
        $response->assertOk();
        $response->assertSee('修正申請テスト');
        $response->assertSee('09:10');
        $response->assertSee('18:15');
    }

    /**
     * @test
     */
    public function 正常系_承認待ち一覧に自分が行った申請が全て表示される(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance1 = $this->createAttendance(
            $user,
            '2026-10-01'
        );

        $attendance2 = $this->createAttendance(
            $user,
            '2026-10-02'
        );

        $this->actingAs($user);

        // Act：1件目の申請
        $this->post(
            route('attendance.correction.store', $attendance1->id),
            [
                'new_date' => '10月01日',
                'new_clock_in' => '09:10',
                'new_clock_out' => '18:10',
                'new_break_in' => ['12:00'],
                'new_break_out' => ['13:00'],
                'comment' => '承認待ち申請1',
            ]
        );

        // Act：2件目の申請
        $this->post(
            route('attendance.correction.store', $attendance2->id),
            [
                'new_date' => '10月01日',
                'new_clock_in' => '09:20',
                'new_clock_out' => '18:20',
                'new_break_in' => ['12:10'],
                'new_break_out' => ['13:10'],
                'comment' => '承認待ち申請2',
            ]
        );

        // Act：申請一覧を表示
        $response = $this->get(
            route('attendance.application.list')
        );

        // Assert
        $response->assertOk();
        $response->assertSee('承認待ち');
        $response->assertSee('承認待ち申請1');
        $response->assertSee('承認待ち申請2');

        $this->assertDatabaseCount(
            'attendance_correction_requests',
            2
        );
    }

    /**
     * @test
     */
    public function 正常系_承認済み一覧に管理者が承認した申請が表示される(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $attendance = $this->createAttendance(
            $user,
            '2026-10-01',
            true
        );

        // Act：一般ユーザーが修正申請
        $this
            ->actingAs($user)
            ->post(
                route('attendance.correction.store', $attendance->id),
                [
                    'new_date' => '10月01日',
                    'new_clock_in' => '09:10',
                    'new_clock_out' => '18:15',
                    'new_break_in' => ['12:10'],
                    'new_break_out' => ['13:10'],
                    'comment' => '承認済み申請テスト',
                ]
            );

        $application = AttendanceCorrectionRequest::firstOrFail();

        // Act：管理者が承認
        $response = $this
            ->actingAs($admin)
            ->post(
                route('admin.application.approve', [
                    'attendance_correct_request_id' => $application->id,
                ])
            );

        // Assert
        $response->assertRedirect(
            route('admin.application.show', [
                'attendance_correct_request_id' => $application->id,
            ])
        );

        $this->assertDatabaseHas('attendance_correction_requests', [
            'id' => $application->id,
            'status' => 1,
        ]);

        // Act：一般ユーザーで申請一覧を表示
        $response = $this
            ->actingAs($user)
            ->get(route('attendance.application.list'));

        // Assert
        $response->assertOk();
        $response->assertSee('承認済');
        $response->assertSee('承認済み申請テスト');
    }

    /**
     * @test
     */
    public function 正常系_申請一覧の詳細から対象の勤怠詳細画面に遷移できる(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance(
            $user,
            '2026-10-01'
        );

        $this->actingAs($user);

        $this->post(
            route('attendance.correction.store', $attendance->id),
            [
                'new_date' => '10月01日',
                'new_clock_in' => '09:10',
                'new_clock_out' => '18:10',
                'new_break_in' => ['12:00'],
                'new_break_out' => ['13:00'],
                'comment' => '詳細遷移テスト',
            ]
        );

        $application = AttendanceCorrectionRequest::firstOrFail();

        // Act：申請一覧を表示
        $response = $this->get(
            route('attendance.application.list')
        );

        // Assert：提供Bladeの詳細リンクを確認
        $response->assertOk();
        $response->assertSee('詳細');
        $response->assertSee(
            url('/application/'.$application->id),
            false
        );

        // Act：提供Bladeのリンク先へアクセス
        $response = $this->get(
            route('attendance.application.detail', $application->id)
        );

        // Assert：正式な勤怠詳細画面へリダイレクト
        $response->assertRedirect(
            route('attendance.show', $attendance->id)
        );

        // Act：勤怠詳細画面を表示
        $response = $this->get(
            route('attendance.show', $attendance->id)
        );

        // Assert
        $response->assertOk();
    }

    private function createAttendance(
        User $user,
        string $date,
        bool $withBreak = false
    ): Attendance {
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => "{$date} 09:00:00",
            'clock_out' => "{$date} 18:00:00",
            'comment' => '元の備考',
        ]);

        if ($withBreak) {
            BreakTime::create([
                'attendance_id' => $attendance->id,
                'break_in' => "{$date} 12:00:00",
                'break_out' => "{$date} 13:00:00",
            ]);
        }

        return $attendance;
    }
}
