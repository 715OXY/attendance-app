<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class D16EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_会員登録時に認証メールが送信される(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => '認証テスト太郎',
            'email' => 'd16-register@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'd16-register@example.com')
            ->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->hasVerifiedEmail());

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_未認証ユーザーは認証案内画面を表示できる(): void
    {
        $user = $this->createUnverifiedUser();

        $response = $this->actingAs($user)
            ->get(route('verification.notice'));

        $response->assertOk()
            ->assertViewIs('auth.verify-email');

        $document = new \DOMDocument;
        $document->loadHTML(
            '<?xml encoding="UTF-8">'.$response->getContent(),
            LIBXML_NOERROR | LIBXML_NOWARNING
        );

        $xpath = new \DOMXPath($document);
        $links = $xpath->query(
            '//a[normalize-space(.)="認証はこちらから"]'
        );

        $this->assertSame(1, $links->length);
        $this->assertSame(
            'http://localhost:8025',
            $links->item(0)->getAttribute('href')
        );

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_認証メールのリンクで認証が完了し勤怠画面を表示できる(): void
    {
        Notification::fake();

        $user = $this->createUnverifiedUser();
        $user->sendEmailVerificationNotification();

        $verificationUrl = null;

        Notification::assertSentTo(
            $user,
            VerifyEmail::class,
            function (VerifyEmail $notification) use ($user, &$verificationUrl): bool {
                $verificationUrl = $notification->toMail($user)->actionUrl;

                return is_string($verificationUrl)
                    && $verificationUrl !== '';
            }
        );

        $this->assertNotNull($verificationUrl);

        $response = $this->actingAs($user)
            ->get($verificationUrl);

        $response->assertRedirect();

        // 認証完了を示すクエリの有無にかかわらず、転送先が勤怠登録画面か確認する。
        $this->assertSame(
            route('attendance.index', [], false),
            parse_url($response->headers->get('Location'), PHP_URL_PATH)
        );

        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $this->get(route('attendance.index'))
            ->assertOk();
    }

    public function test_未認証ユーザーは認証メールを再送できる(): void
    {
        Notification::fake();

        $user = $this->createUnverifiedUser();

        $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'));

        Notification::assertSentTo($user, VerifyEmail::class);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_署名が改ざんされたリンクでは認証されない(): void
    {
        $user = $this->createUnverifiedUser();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        // 署名生成後にクエリを追加し、署名検証に失敗するURLにする。
        $tamperedUrl = $verificationUrl.'&tampered=1';

        $this->actingAs($user)
            ->get($tamperedUrl)
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    private function createUnverifiedUser(): User
    {
        return User::factory()->create([
            'admin_status' => false,
            'email_verified_at' => null,
        ]);
    }
}
