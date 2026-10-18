<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendanceRecordPolicy
{
    /**
     * 管理者には、このPolicyによる認可対象の操作を許可する。
     *
     * @param  User  $user  操作するユーザー
     * @param  string  $ability  判定対象の操作名
     * @return bool|null 管理者ならtrue。それ以外は個別の判定に委ねる
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->admin_status) {
            return true;
        }

        return null;
    }

    /**
     * 本人の勤怠の更新を許可する。
     *
     * @param  User  $user  操作するユーザー
     * @param  Attendance  $attendance  更新対象の勤怠
     * @return bool 勤怠の所有者であるか
     */
    public function update(User $user, Attendance $attendance): bool
    {
        return $user->id === $attendance->user_id;
    }

    /**
     * 本人の勤怠の削除を許可する。
     *
     * @param  User  $user  操作するユーザー
     * @param  Attendance  $attendance  削除対象の勤怠
     * @return bool 勤怠の所有者であるか
     */
    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->id === $attendance->user_id;
    }
}
