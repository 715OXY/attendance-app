<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class D15AdminCorrectionApprovalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function 正常系_承認待ちに全ユーザーの未承認修正申請が表示される(): void
    {
        // Arrange
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user1 = User::factory()->create([
            'name' => 'テスト太郎',
            'admin_status' => false,
        ]);

        $user2 = User::factory()->create([
            'name' => 'テスト花子',
            'admin_status' => false,
        ]);

        $attendance1 = $this->createAttendance(
            $user1,
            '2026-10-01'
        );

        $attendance2 = $this->createAttendance(
            $user2,
            '2026-10-02'
        );

        $application1 = $this->createCorrectionRequest(
            $user1,
            $attendance1,
            '09:10',
            '18:10',
            '12:10',
            '13:10',
            '太郎の承認待ち申請'
        );

        $application2 = $this->createCorrectionRequest(
            $user2,
            $attendance2,
            '09:20',
            '18:20',
            '12:20',
            '13:20',
            '花子の承認待ち申請'
        );

        // Act
        $response = $this
            ->actingAs($admin)
            ->get(route('attendance.application.list'));

        // Assert
        $response->assertOk();

        $response->assertSee('承認待ち');

        $response->assertSee('テスト太郎');
        $response->assertSee('太郎の承認待ち申請');

        $response->assertSee('テスト花子');
        $response->assertSee('花子の承認待ち申請');

        $this->assertDatabaseHas(
            'attendance_correction_requests',
            [
                'id' => $application1->id,
                'status' => 0,
            ]
        );

        $this->assertDatabaseHas(
            'attendance_correction_requests',
            [
                'id' => $application2->id,
                'status' => 0,
            ]
        );
    }

    /**
     * @test
     */
    public function 正常系_承認済みに全ユーザーの承認済み修正申請が表示される(): void
    {
        // Arrange
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user1 = User::factory()->create([
            'name' => '承認済み太郎',
            'admin_status' => false,
        ]);

        $user2 = User::factory()->create([
            'name' => '承認済み花子',
            'admin_status' => false,
        ]);

        $attendance1 = $this->createAttendance(
            $user1,
            '2026-10-01'
        );

        $attendance2 = $this->createAttendance(
            $user2,
            '2026-10-02'
        );

        $application1 = $this->createCorrectionRequest(
            $user1,
            $attendance1,
            '09:10',
            '18:10',
            '12:10',
            '13:10',
            '太郎の承認済み申請'
        );

        $application2 = $this->createCorrectionRequest(
            $user2,
            $attendance2,
            '09:20',
            '18:20',
            '12:20',
            '13:20',
            '花子の承認済み申請'
        );

        // 承認済み状態を準備
        $application1->update([
            'status' => 1,
        ]);

        $application2->update([
            'status' => 1,
        ]);

        // Act
        $response = $this
            ->actingAs($admin)
            ->get(route('attendance.application.list'));

        // Assert
        $response->assertOk();

        $response->assertSee('承認済み');

        $response->assertSee('承認済み太郎');
        $response->assertSee('太郎の承認済み申請');

        $response->assertSee('承認済み花子');
        $response->assertSee('花子の承認済み申請');

        $this->assertDatabaseHas(
            'attendance_correction_requests',
            [
                'id' => $application1->id,
                'status' => 1,
            ]
        );

        $this->assertDatabaseHas(
            'attendance_correction_requests',
            [
                'id' => $application2->id,
                'status' => 1,
            ]
        );
    }

    /**
     * @test
     */
    public function 正常系_修正申請の詳細内容が正しく表示される(): void
    {
        // Arrange
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'テスト太郎',
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance(
            $user,
            '2026-10-01'
        );

        $application = $this->createCorrectionRequest(
            $user,
            $attendance,
            '09:10',
            '18:15',
            '12:10',
            '13:10',
            '管理者承認画面テスト'
        );

        // Act
        $response = $this
            ->actingAs($admin)
            ->get(
                route('admin.application.show', [
                    'attendance_correct_request_id' => $application->id,
                ])
            );

        // Assert
        $response->assertOk();

        $response->assertSee('テスト太郎');

        $response->assertSee('09:10');
        $response->assertSee('18:15');

        $response->assertSee('12:10');
        $response->assertSee('13:10');

        $response->assertSee(
            '管理者承認画面テスト'
        );
    }

    /**
     * @test
     */
    public function 正常系_修正申請を承認すると勤怠情報に申請内容が反映される(): void
    {
        // Arrange
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'テスト太郎',
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance(
            $user,
            '2026-10-01'
        );

        $application = $this->createCorrectionRequest(
            $user,
            $attendance,
            '09:10',
            '18:15',
            '12:10',
            '13:10',
            '承認後の備考'
        );

        // 承認前は元の勤怠情報のまま
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '2026-10-01 09:00:00',
            'clock_out' => '2026-10-01 18:00:00',
            'comment' => '元の備考',
        ]);

        $this->assertDatabaseHas('breaks', [
            'attendance_id' => $attendance->id,
            'break_in' => '2026-10-01 12:00:00',
            'break_out' => '2026-10-01 13:00:00',
        ]);

        // Act
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

        // 申請が承認済みになる
        $this->assertDatabaseHas(
            'attendance_correction_requests',
            [
                'id' => $application->id,
                'status' => 1,
            ]
        );

        // 出勤・退勤・備考が修正内容に更新される
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '2026-10-01 09:10:00',
            'clock_out' => '2026-10-01 18:15:00',
            'comment' => '承認後の備考',
        ]);

        // 休憩時間も修正内容に更新される
        $this->assertDatabaseHas('breaks', [
            'attendance_id' => $attendance->id,
            'break_in' => '2026-10-01 12:10:00',
            'break_out' => '2026-10-01 13:10:00',
        ]);

        // 元の休憩時間は残っていない
        $this->assertDatabaseMissing('breaks', [
            'attendance_id' => $attendance->id,
            'break_in' => '2026-10-01 12:00:00',
            'break_out' => '2026-10-01 13:00:00',
        ]);
    }

    private function createAttendance(
        User $user,
        string $date
    ): Attendance {
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => "{$date} 09:00:00",
            'clock_out' => "{$date} 18:00:00",
            'comment' => '元の備考',
        ]);

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in' => "{$date} 12:00:00",
            'break_out' => "{$date} 13:00:00",
        ]);

        return $attendance;
    }

    private function createCorrectionRequest(
        User $user,
        Attendance $attendance,
        string $clockIn,
        string $clockOut,
        string $breakIn,
        string $breakOut,
        string $comment
    ): AttendanceCorrectionRequest {
        $date = Carbon::parse(
            $attendance->date
        )->format('m月d日');

        $response = $this
            ->actingAs($user)
            ->post(
                route(
                    'attendance.correction.store',
                    $attendance->id
                ),
                [
                    'new_date' => $date,
                    'new_clock_in' => $clockIn,
                    'new_clock_out' => $clockOut,
                    'new_break_in' => [$breakIn],
                    'new_break_out' => [$breakOut],
                    'comment' => $comment,
                ]
            );

        $response->assertSessionHasNoErrors();

        return AttendanceCorrectionRequest::where(
            'attendance_id',
            $attendance->id
        )
            ->latest('id')
            ->firstOrFail();
    }
}
