<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StaffMonthlyAttendanceBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceExportController extends Controller
{
    /**
     * 指定スタッフの月次勤怠をCSVで出力する。
     *
     * @param  Request  $request  スタッフIDと対象年月を含むリクエスト
     * @param  StaffMonthlyAttendanceBuilder  $builder  月次データ生成処理
     * @return StreamedResponse CSVダウンロードレスポンス
     */
    public function export(
        Request $request,
        StaffMonthlyAttendanceBuilder $builder
    ): StreamedResponse {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'integer'],
            'year_month' => ['required', 'date_format:Y-m'],
        ]);

        abort_if(
            $validator->fails(),
            400,
            'CSV出力条件が正しくありません。'
        );

        $validated = $validator->validated();

        $user = User::where('admin_status', false)
            ->findOrFail((int) $validated['user_id']);

        $date = Carbon::createFromFormat(
            '!Y-m',
            $validated['year_month'],
            'Asia/Tokyo'
        );

        $records = $builder->build($user, $date);

        $fileName = sprintf(
            'attendance_%d_%s.csv',
            $user->id,
            $date->format('Y-m')
        );

        return response()->streamDownload(
            function () use ($records): void {
                $this->writeCsv($records);
            },
            $fileName,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    /**
     * 月次勤怠データをCSV形式で出力ストリームへ書き込む。
     *
     * @param  Collection<int, array<string, mixed>>  $records  月次勤怠データ
     */
    private function writeCsv(Collection $records): void
    {
        $stream = fopen('php://output', 'w');

        if ($stream === false) {
            return;
        }

        try {
            // Excelで日本語が文字化けしにくいようUTF-8 BOMを付与する。
            fwrite($stream, "\xEF\xBB\xBF");

            fputcsv(
                $stream,
                ['日付', '出勤', '退勤', '休憩', '合計'],
                ',',
                '"',
                ''
            );

            $records->each(function (array $attendance) use ($stream): void {
                fputcsv(
                    $stream,
                    [
                        $attendance['date'],
                        $attendance['clock_in'],
                        $attendance['clock_out'],
                        $this->formatCsvTime($attendance['total_break_time']),
                        $this->formatCsvTime($attendance['total_time']),
                    ],
                    ',',
                    '"',
                    ''
                );
            });
        } finally {
            fclose($stream);
        }
    }

    /**
     * 時間文字列をCSV用の時間・分表示へ整形する。
     *
     * @param  string|null  $time  整形対象の時間。未確定の場合はnull
     * @return string G:i形式の時間、または空文字
     */
    private function formatCsvTime(?string $time): string
    {
        return $time
            ? Carbon::parse($time)->format('G:i')
            : '';
    }
}
