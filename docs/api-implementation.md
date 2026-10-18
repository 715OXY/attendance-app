# 公開APIの実装補足

要件シートの「API仕様書（応用）」に記載された名称・実装例と、提出時の実装の対応を示します。提供された要件の原文は保持し、実装上の名前と動作を本資料で補足します。

## 名称の対応

| 対象 | 提供仕様書内の表記 | 実装 |
| --- | --- | --- |
| 勤怠Model | AttendanceRecord | App\Models\Attendance |
| 勤怠テーブル | attendance_records | attendances |
| 勤怠への外部キー | attendance_record_id | attendance_id |
| 勤怠からの修正申請リレーション | applications | correctionRequests |
| 修正申請からの休憩リレーション | ― | correctionBreaks |
| APIのURL | /api/v1/attendance-records | 変更なし |
| ルートパラメータ | attendanceRecord | 変更なし。Attendance $attendanceRecordでバインド |
| JSONの修正申請フィールド | applications | 変更なし。correctionRequestsを変換して返却 |
| API Controller | Api\V1\AttendanceRecordController | 変更なし |
| API Resource | AttendanceRecordResource | 変更なし |
| 認可Policy | AttendanceRecordPolicy | 変更なし。対象ModelはAttendance |

Model名・DB名と、公開URL・JSONフィールド名は別のものです。APIのURLや返却フィールド名は、Model名の変更に合わせて置換しません。

## エンドポイント

| メソッド | URI | 処理 | 認証・認可 | 成功時 |
| --- | --- | --- | --- | --- |
| GET | /api/v1/attendance-records | index | 認証不要 | 200 |
| GET | /api/v1/attendance-records/{attendanceRecord} | show | 認証不要 | 200 |
| POST | /api/v1/attendance-records | store | auth:sanctum | 201 |
| PUT | /api/v1/attendance-records/{attendanceRecord} | update | auth:sanctum、本人または管理者 | 200 |
| PATCH | /api/v1/attendance-records/{attendanceRecord} | update | auth:sanctum、本人または管理者 | 200 |
| DELETE | /api/v1/attendance-records/{attendanceRecord} | destroy | auth:sanctum、本人または管理者 | 204、本文なし |

基本設計書には、上記とは別に認証ユーザーを返すGET /api/user（auth:sanctum、Closure）も記載しています。

Bearer認証ではAuthorizationヘッダーにトークンを指定します。JSONの送受信にはAccept: application/jsonとContent-Type: application/jsonを指定します。トークンの実値は資料へ掲載しません。

## 検索・並び順

IndexAttendanceRecordRequestで次のパラメータを検証します。すべて任意・NULL可です。

| パラメータ | 条件 |
| --- | --- |
| user_id | 整数、users.idに存在 |
| date | YYYY-MM-DD形式 |
| month | YYYY-MM形式 |
| page | 整数、1以上 |
| per_page | 整数、1以上100以下。未指定・NULL時は20 |

複数の検索条件はAND条件で適用します。dateの降順、同日の場合はidの降順で並べます。ページネーションリンクには検索クエリを引き継ぎます。

## 関連データとレスポンス

| 処理 | 読み込むリレーション |
| --- | --- |
| 一覧 | user、breaks |
| 詳細 | user、breaks、correctionRequests.correctionBreaks |
| 登録後・更新後 | user、breaks |

- userを読み込んだ場合はuser_nameとuserを返します。
- breaksは一覧・詳細・登録後・更新後のいずれでも返します。
- JSONのapplicationsは、correctionRequestsを読み込む詳細取得時に返します。一覧・登録後・更新後では省略します。
- 詳細取得で承認待ち申請がある場合は、その中の最新idの申請を使い、出退勤時刻・備考・休憩・合計時間を返します。正式なDBレコードを変更する処理ではありません。
- 一覧では修正申請を読み込まず、正式な勤怠・休憩情報を返します。
- dateはYYYY-MM-DD、出退勤・休憩時刻はHH:MM:SSで返します。
- total_timeとtotal_break_timeはHH:MM形式です。出勤・退勤の両方が揃っていない場合はNULLです。終了していない休憩は合計から除外します。
- 一覧はdata・links・metaを含むページネーションレスポンス、詳細・登録・更新はdataで包んだ単一リソースを返します。

## 登録・更新の検証と保存

### POST

- StoreAttendanceRecordRequestで検証します。
- dateとclock_inは必須です。
- dateは認証ユーザーの同一日の勤怠と重複できません。
- clock_outとcommentは任意・NULL可です。commentは文字列・255文字以内です。
- clock_outを入力した場合、clock_inより後であることを検証します。
- user_idは認証ユーザーから取得します。送信された任意の所有者IDで登録しません。
- 送信された日付と時刻を組み合わせ、clock_in・clock_outをDATETIMEとして保存します。

### PUT / PATCH

- どちらもUpdateAttendanceRecordRequestを使用し、未送信項目を保持する部分更新に対応します。空の本文でも既存値を保持します。
- dateとclock_inは送信時に必須・NULL不可です。
- clock_outとcommentは送信時のみ検証し、明示的なNULLへの更新を許可します。
- dateの重複判定は、更新対象の勤怠自身を除外し、勤怠所有者のuser_idを基準にします。管理者自身のuser_idを基準にはしません。
- 送信値と既存値を組み合わせ、出勤・退勤が両方ある場合に退勤が出勤より後であることを検証します。
- dateだけを変更した場合は、出退勤の時刻部分を保持し、日付部分を新しい勤怠日に合わせます。

## 認可と削除

- AttendanceRecordPolicy.before()は管理者に操作を許可します。一般ユーザーにはupdate・deleteで所有者一致を求めます。
- 更新の認可はUpdateAttendanceRecordRequest.authorize()からPolicyを呼び出し、入力検証より先に行います。
- 削除の認可はController.destroy()のauthorize('delete', ...)で行います。
- 勤怠削除時は外部キーのCASCADEによりbreaks・attendance_correction_requests・attendance_correction_breaksも削除します。

## エラー

| HTTPステータス | 条件・形式 |
| --- | --- |
| 401 | 未認証の保護対象APIアクセス。messageを含むJSON |
| 403 | 認証済みだが操作権限がない場合。errorを含むJSON |
| 404 | バインド対象の勤怠が存在しない場合。errorを含むJSON |
| 422 | 入力検証エラー。messageとerrorsを含むJSON |
| 429 | スロットリングの上限に達した場合 |

勤怠の404は「勤怠情報が見つかりませんでした。」、認可の403は「この操作を実行する権限がありません。」を返します。api/*の例外はAcceptヘッダーにかかわらずJSON形式で返します。

## 提供仕様書を読む際の補足

- 「本人のみ」と記載された箇所も、更新・削除の実装はPolicyに従い「本人または管理者」です。
- 書き込み対象はPOST・PUT・PATCH・DELETEです。
- ResourceのJSONフィールドapplicationsとModelのリレーションcorrectionRequestsを区別します。
- API一覧でもbreaksを返します。「関連データは詳細時のみ」という説明はapplicationsに限って適用します。
- Sanctum::actingAs()はテスト用の認証設定で、Bearerトークンを発行する処理ではありません。実トークンを使用した登録も自動テストの対象です。
- パッケージはcomposer.lockからインストールします。既存プロジェクトの環境構築でSanctumを改めてcomposer requireする必要はありません。

## 実装確認に用いたテスト

- D17：一覧・詳細・検索・ページネーション・承認待ち申請の返却
- D18：登録・更新・部分更新・削除・入力検証・関連データの削除
- D19：Sanctum認証・本人／管理者の認可・JSONエラー
- D21：件数増加時の一覧APIのSELECT回数
