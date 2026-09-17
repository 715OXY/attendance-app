# COACHTECH 勤怠管理アプリ

## 概要

ユーザーの勤怠管理を目的としたWebアプリケーションです。

一般ユーザーは、出勤・退勤・休憩の打刻、勤怠情報の確認、勤怠修正申請などを行うことができます。

管理者は、スタッフの勤怠情報の確認・修正、勤怠修正申請の承認などを行うことができます。

> **Note**  
> 本READMEは開発進行に合わせて随時更新します。

---

## 環境構築

### 1. リポジトリをクローン

```bash
git clone <Repository URL>
cd <Repository Name>
```

### 2. Composerパッケージのインストール

```bash
docker run --rm \
  -u "$(id -u):$(id -g)" \
  -v "$(pwd):/var/www/html" \
  -w /var/www/html \
  -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
  laravelsail/php82-composer:latest \
  composer install
```

### 3. 環境変数ファイルの作成

```bash
cp .env.example .env
```

`.env` のデータベースおよびメール設定を、開発環境に合わせて設定します。

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

### 4. Laravel Sailを起動

```bash
./vendor/bin/sail up -d
```

### 5. アプリケーションキーを生成

```bash
./vendor/bin/sail artisan key:generate
```

### 6. マイグレーション・シーディングを実行

```bash
./vendor/bin/sail artisan migrate --seed
```

### 7. フロントエンド環境を構築

```bash
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

以上で環境構築は完了です。

---

## 使用技術（実行環境）

| 技術            | バージョン・用途     |
| --------------- | -------------------- |
| PHP             | 8.2                  |
| Laravel         | 10.x                 |
| MySQL           | 8.4                  |
| Laravel Sail    | Docker開発環境       |
| Docker          | コンテナ環境         |
| Laravel Fortify | 認証機能             |
| Vite            | フロントエンドビルド |

※ 指定された技術スタック以外のComposerパッケージは、原則として追加しません。

※ 日本語化については `laravel-lang/*` 等の外部翻訳パッケージを使用せず、`lang/` 配下へのメッセージファイルの配置によって対応します。

---

## ER図

**TODO：データベース設計完了後にER図を掲載します。**

掲載予定例：

```text
docs/er-diagram.png
```

---

## URL

| サービス         | URL                    |
| ---------------- | ---------------------- |
| アプリケーション | http://localhost/      |
| phpMyAdmin       | http://localhost:8080/ |
| Mailpit          | http://localhost:8025/ |

---

## ログイン情報

### 一般ユーザー

| ユーザー | メールアドレス    | パスワード |
| -------- | ----------------- | ---------- |
| user1    | user1@example.com | password   |
| user2    | user2@example.com | password   |

### 管理者ユーザー

| ユーザー | メールアドレス    | パスワード |
| -------- | ----------------- | ---------- |
| user3    | user3@example.com | password   |

※ 上記ユーザーはSeederによって作成します。

※ `user1`、`user2` は一般ユーザー、`user3` は管理者ユーザー（`admin_status = true`）として作成します。

※ 各ユーザーはメール認証済みの状態で作成します。

---

## 主な機能

### 一般ユーザー

- 会員登録・ログイン
- 勤怠打刻
- 勤怠一覧表示
- 勤怠詳細表示
- 勤怠修正申請
- 修正申請一覧表示

### 管理者

- 管理者ログイン
- 日次勤怠一覧表示
- 勤怠詳細表示・修正
- スタッフ一覧表示
- スタッフ別月次勤怠一覧表示
- 勤怠修正申請の確認・承認

> **TODO**  
> 各機能の実装完了時に、実際の実装内容に合わせて更新します。

---

## テスト

**TODO：テスト実装後に、テストの実行方法・対象機能・確認内容を追記します。**

実行コマンドは、テスト環境確定後に記載します。

---

## 補足

フロントエンドのBladeテンプレート・CSS・JavaScriptについては、課題で提供された `resources/` を使用します。

バックエンドについては、要件に基づいて以下を実装します。

- Route
- Controller
- Model
- Migration
- FormRequest
- Policy / Middleware等の認可処理
- Seeder / Factory
- Test

---

## README更新予定

本READMEは、開発工程に合わせて更新します。

| 開発工程         | READMEへの主な追記内容                      |
| ---------------- | ------------------------------------------- |
| 環境構築         | Sail、Fortify、日本語化、環境構築手順       |
| データベース設計 | ER図、Seeder、ログインユーザー              |
| 認証機能         | 一般・管理者認証に関する補足                |
| 基本機能実装     | 実装済み機能                                |
| 基本機能テスト   | テスト方法・テスト内容                      |
| 応用機能         | メール認証、CSV出力等                       |
| 最終提出前       | URL、環境構築手順、ログイン情報等の最終確認 |
