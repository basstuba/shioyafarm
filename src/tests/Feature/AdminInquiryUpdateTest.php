<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\TestCase;

/**
 * 管理画面のお問い合わせの対応済みチェック（admin.inquiries.update）のテスト
 */
class AdminInquiryUpdateTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * 公開側のフォームから届いたお問い合わせは、未対応で始まる
     */
    #[Test]
    public function 公開側から届いたお問い合わせは未対応で始まる(): void
    {
        $this->post(route('top.inquiry.store'), [
            'last_name' => '山田',
            'first_name' => '太郎',
            'email' => 'yamada@example.com',
            'detail' => 'こんにちは',
        ]);

        $inquiry = Inquiry::firstOrFail();
        $this->assertFalse($inquiry->addressed);
        $this->assertDatabaseHas('inquiries', ['id' => $inquiry->id, 'addressed' => 0]);
    }

    /**
     * チェックを入れると対応済みになり、外すと未対応に戻る。JSON を返す
     */
    #[Test]
    public function チェックを入れると対応済みになり外すと未対応に戻る(): void
    {
        $this->loginAsAdmin();
        $inquiry = Inquiry::factory()->create();

        $this->postJson(route('admin.inquiries.update'), ['id' => $inquiry->id, 'addressed' => true])
            ->assertOk()
            ->assertExactJson(['id' => $inquiry->id, 'addressed' => true]);
        $this->assertTrue($inquiry->fresh()->addressed);

        $this->postJson(route('admin.inquiries.update'), ['id' => $inquiry->id, 'addressed' => false])
            ->assertOk()
            ->assertExactJson(['id' => $inquiry->id, 'addressed' => false]);
        $this->assertFalse($inquiry->fresh()->addressed);
    }

    /**
     * すでに同じ値のときも成功する
     */
    #[Test]
    public function 同じ値の更新も成功する(): void
    {
        $this->loginAsAdmin();
        $inquiry = Inquiry::factory()->addressed()->create();

        $this->postJson(route('admin.inquiries.update'), ['id' => $inquiry->id, 'addressed' => true])->assertOk();

        $this->assertTrue($inquiry->fresh()->addressed);
    }

    /**
     * 更新するのは指定したお問い合わせだけで、他のお問い合わせは変わらない
     */
    #[Test]
    public function 他のお問い合わせは変わらない(): void
    {
        $this->loginAsAdmin();
        $target = Inquiry::factory()->create();
        $other = Inquiry::factory()->create();

        $this->postJson(route('admin.inquiries.update'), ['id' => $target->id, 'addressed' => true])->assertOk();

        $this->assertTrue($target->fresh()->addressed);
        $this->assertFalse($other->fresh()->addressed);
    }

    /**
     * 存在しないお問い合わせは、リクエストの検証ではなく更新の処理が判定し、404 を返す。他のお問い合わせは変わらない
     */
    #[Test]
    public function 存在しないお問い合わせは404で他は変わらない(): void
    {
        $this->loginAsAdmin();
        $inquiry = Inquiry::factory()->create();

        foreach ([$inquiry->id + 100, 999999] as $id) {
            $this->postJson(route('admin.inquiries.update'), ['id' => $id, 'addressed' => true])
                ->assertNotFound()
                ->assertExactJson(['message' => 'お問い合わせが見つかりません。']);
        }

        $this->assertFalse($inquiry->fresh()->addressed);
    }

    /**
     * 入力の誤りは、リダイレクトではなく JSON（422）で返る。どのお問い合わせも変わらない
     */
    #[Test]
    public function 入力の誤りはjsonの422で返る(): void
    {
        $this->loginAsAdmin();
        $inquiry = Inquiry::factory()->create();

        $this->postJson(route('admin.inquiries.update'), ['addressed' => true])->assertStatus(422)->assertJsonValidationErrors('id');
        $this->postJson(route('admin.inquiries.update'), ['id' => $inquiry->id])->assertStatus(422)->assertJsonValidationErrors('addressed');
        $this->postJson(route('admin.inquiries.update'), ['id' => $inquiry->id, 'addressed' => 'abc'])->assertStatus(422)->assertJsonValidationErrors('addressed');
        foreach ([0, -1, 1.5, '1e3', '', 'abc'] as $badId) {
            $this->postJson(route('admin.inquiries.update'), ['id' => $badId, 'addressed' => true])->assertStatus(422)->assertJsonValidationErrors('id');
        }
        $this->post(route('admin.inquiries.update'), ['id' => 0, 'addressed' => true], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id');

        $this->assertFalse($inquiry->fresh()->addressed);
    }

    /**
     * 未ログイン・会員としてのログインでは変更できない
     */
    #[Test]
    public function 未ログインと会員は対応済みを変更できない(): void
    {
        $inquiry = Inquiry::factory()->create();

        $this->post(route('admin.inquiries.update'), ['id' => $inquiry->id, 'addressed' => true])->assertRedirect(route('admin.login'));
        $this->postJson(route('admin.inquiries.update'), ['id' => $inquiry->id, 'addressed' => true])->assertUnauthorized();
        $this->assertFalse($inquiry->fresh()->addressed);

        $this->loginAsMember();
        $this->post(route('admin.inquiries.update'), ['id' => $inquiry->id, 'addressed' => true])->assertRedirect(route('admin.login'));
        $this->assertFalse($inquiry->fresh()->addressed);
    }

    /**
     * CSRFトークンがないとき（419）は変更されない（テストでは検証がスキップされるため、有効にして確認する）
     */
    #[Test]
    public function csrfトークンがないと変更できない(): void
    {
        $this->app['env'] = 'local';
        $this->loginAsAdmin();
        $inquiry = Inquiry::factory()->create();

        $this->postJson(route('admin.inquiries.update'), ['id' => $inquiry->id, 'addressed' => true])->assertStatus(419);

        $this->assertFalse($inquiry->fresh()->addressed);
    }

    /**
     * 対応済みにしても、お客様にメールは送られない
     */
    #[Test]
    public function 対応済みにしてもメールは送られない(): void
    {
        Mail::fake();
        $this->loginAsAdmin();
        $inquiry = Inquiry::factory()->create();

        $this->postJson(route('admin.inquiries.update'), ['id' => $inquiry->id, 'addressed' => true])->assertOk();

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }
}
