<?php

namespace Tests\Feature;

use App\Http\Responses\ExpiredPageResponder;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AdminPurchaseTestHelpers;
use Tests\Concerns\MaterialTestHelpers;
use Tests\TestCase;

/**
 * 管理画面の資材の追加・修正・削除（admin.materials.store・update・delete）のテスト
 */
class AdminMaterialFormTest extends TestCase
{
    use AdminPurchaseTestHelpers;
    use MaterialTestHelpers;
    use RefreshDatabase;

    /**
     * 追加を送る（一覧のモーダルから送る形）
     *
     * @param  array<string, mixed>  $overrides
     */
    private function store(array $overrides = []): TestResponse
    {
        return $this->from(route('admin.materials.index'))->post(route('admin.materials.store'), [
            'name' => '化粧箱 3kg用',
            'stock' => '30',
            'location' => '倉庫2階',
            'threshold' => '5',
            ...$overrides,
        ]);
    }

    /**
     * 修正を送る
     *
     * @param  array<string, mixed>  $overrides
     */
    private function update(Material $material, array $overrides = []): TestResponse
    {
        return $this->from(route('admin.materials.index'))->post(route('admin.materials.update'), [
            'id' => (string) $material->id,
            'name' => '修正した資材',
            'stock' => '25',
            'location' => '修正した置き場所',
            'threshold' => '8',
            ...$overrides,
        ]);
    }

    /**
     * 削除を送る
     */
    private function deleteMaterial(mixed $id): TestResponse
    {
        return $this->from(route('admin.materials.index'))->delete(route('admin.materials.delete'), ['id' => $id]);
    }

    /**
     * 追加すると保存され、一覧に戻って緑の案内が出て、表の最後に出る
     */
    #[Test]
    public function 資材を追加すると一覧に戻って案内が出て表の最後に表示される(): void
    {
        $this->loginAsAdmin();
        Material::factory()->create(['name' => '既存の資材']);

        $this->store()
            ->assertRedirect(route('admin.materials.index'))
            ->assertSessionHas('list_status', '資材を追加しました。')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('materials', ['name' => '化粧箱 3kg用', 'stock' => 30, 'location' => '倉庫2階', 'threshold' => 5]);
        $response = $this->get(route('admin.materials.index'))->assertOk();
        $response->assertSee('資材を追加しました。')->assertSee('text-accent-hover', false)->assertSeeInOrder(['既存の資材', '化粧箱 3kg用']);
        // 成功したときはモーダルを閉じた状態
        $this->assertFalse($this->formState($response->getContent())['formOpen']);
    }

    /**
     * 修正すると保存され、一覧に戻って案内が出る。並び順は変わらず、在庫数を基準値より少なくすると「残りわずか」になり、戻すと消える
     */
    #[Test]
    public function 資材を修正すると一覧に戻って案内が出て並び順は変わらない(): void
    {
        $this->loginAsAdmin();
        $first = Material::factory()->create(['name' => 'UAT箱A', 'stock' => 40, 'threshold' => 10]);
        $second = Material::factory()->create(['name' => 'UAT箱B', 'stock' => 40, 'threshold' => 10]);

        $this->update($first, ['name' => 'UAT箱A', 'stock' => '5', 'location' => '倉庫', 'threshold' => '10'])
            ->assertRedirect(route('admin.materials.index'))
            ->assertSessionHas('list_status', '資材を修正しました。')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('materials', ['id' => $first->id, 'stock' => 5, 'location' => '倉庫']);
        $response = $this->get(route('admin.materials.index'))->assertOk();
        $this->assertSame([$first->id, $second->id], $response->viewData('materials')->pluck('id')->all());
        $response->assertSee('資材を修正しました。')->assertSee('残りわずか');

        $this->update($first, ['name' => 'UAT箱A', 'stock' => '40', 'location' => '倉庫', 'threshold' => '10']);
        $this->get(route('admin.materials.index'))->assertDontSee('残りわずか</span>', false);
    }

    /**
     * 修正で資材名を変えずに保存しても、自分自身とは重複にならない。ほかの資材と同じ名前にするとエラー
     */
    #[Test]
    public function 修正のユニークチェックは自分自身を除く(): void
    {
        $this->loginAsAdmin();
        $a = Material::factory()->create(['name' => 'UAT箱A', 'stock' => 1]);
        Material::factory()->create(['name' => 'UAT箱B']);

        $this->update($a, ['name' => 'UAT箱A', 'stock' => '2'])->assertSessionHasNoErrors()->assertSessionHas('list_status');
        $this->assertSame(2, $a->fresh()->stock);

        $this->update($a, ['name' => 'UAT箱B', 'stock' => '3'])
            ->assertRedirect(route('admin.materials.index'))
            ->assertSessionHasErrors(['name' => 'その資材名はすでに登録されています']);
        $this->assertSame('UAT箱A', $a->fresh()->name);
        $this->assertSame(2, $a->fresh()->stock);
    }

    /**
     * 追加で、すでにある資材名はエラーになる
     */
    #[Test]
    public function 追加で同じ資材名はエラーになる(): void
    {
        $this->loginAsAdmin();
        Material::factory()->create(['name' => 'UAT箱A']);

        $this->store(['name' => 'UAT箱A'])
            ->assertRedirect(route('admin.materials.index'))
            ->assertSessionHasErrors(['name' => 'その資材名はすでに登録されています']);

        $this->assertDatabaseCount('materials', 1);
    }

    /**
     * 入力の誤りでは資材は変わらず、項目ごとのエラーになる
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $errors
     */
    #[Test]
    #[DataProvider('invalidInputs')]
    public function 入力の誤りは項目ごとのエラーになり資材は変わらない(array $input, array $errors): void
    {
        $this->loginAsAdmin();
        $material = Material::factory()->create(['name' => 'UAT箱A']);

        $this->store($input)->assertRedirect(route('admin.materials.index'))->assertSessionHasErrors($errors);
        $this->update($material, $input)->assertRedirect(route('admin.materials.index'))->assertSessionHasErrors($errors);

        $this->assertDatabaseCount('materials', 1);
        $this->assertSame('UAT箱A', $material->fresh()->name);
    }

    /**
     * 入力の誤りの組み合わせとエラー文（AC-10）
     *
     * @return array<string, array{array<string, mixed>, array<string, string>}>
     */
    public static function invalidInputs(): array
    {
        return [
            '全部空' => [
                ['name' => '', 'stock' => '', 'location' => '', 'threshold' => ''],
                ['name' => '資材名を入力してください', 'stock' => '在庫数を入力してください', 'location' => '置き場所を入力してください', 'threshold' => '基準値を入力してください'],
            ],
            '資材名51文字・置き場所101文字' => [
                ['name' => str_repeat('あ', 51), 'location' => str_repeat('い', 101)],
                ['name' => '資材名は50文字以内で入力してください', 'location' => '置き場所は100文字以内で入力してください'],
            ],
            '在庫数が負' => [['stock' => '-1'], ['stock' => '在庫数は0以上の整数で入力してください']],
            '基準値が小数' => [['threshold' => '1.5'], ['threshold' => '基準値は0以上の整数で入力してください']],
            '在庫数が文字' => [['stock' => 'abc'], ['stock' => '在庫数は0以上の整数で入力してください']],
            '在庫数が99999超' => [['stock' => '100000'], ['stock' => '在庫数は99999以下で入力してください']],
            '基準値が99999超' => [['threshold' => '100000'], ['threshold' => '基準値は99999以下で入力してください']],
            '基準値が負' => [['threshold' => '-1'], ['threshold' => '基準値は0以上の整数で入力してください']],
            '前後の空白だけ（半角・全角）' => [
                ['name' => " \u{3000} ", 'location' => "\u{3000}\u{3000}"],
                ['name' => '資材名を入力してください', 'location' => '置き場所を入力してください'],
            ],
            '配列を送る' => [['name' => ['a'], 'stock' => ['1']], ['name' => '資材名は文字で入力してください', 'stock' => '在庫数は0以上の整数で入力してください']],
        ];
    }

    /**
     * 境界値（資材名50文字・置き場所100文字・0・99999）はちょうどで保存できる
     */
    #[Test]
    public function 境界値ちょうどは保存できる(): void
    {
        $this->loginAsAdmin();
        $name = str_repeat('あ', 50);
        $location = str_repeat('い', 100);

        $this->store(['name' => $name, 'location' => $location, 'stock' => '0', 'threshold' => '99999'])
            ->assertSessionHasNoErrors()->assertSessionHas('list_status', '資材を追加しました。');
        $this->store(['name' => '最大', 'stock' => '99999', 'threshold' => '0'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('materials', ['name' => $name, 'location' => $location, 'stock' => 0, 'threshold' => 99999]);
        $this->assertDatabaseHas('materials', ['name' => '最大', 'stock' => 99999, 'threshold' => 0]);
        $this->get(route('admin.materials.index'))->assertSee('99,999');
    }

    /**
     * 全角の数字は半角にして保存し、前後の空白（全角を含む）は取り除く
     */
    #[Test]
    public function 全角の数字は半角にして保存し前後の空白は取り除く(): void
    {
        $this->loginAsAdmin();

        $this->store(['name' => "\u{3000} 全角箱 \u{3000}", 'stock' => '４０', 'location' => "\u{3000}倉庫 奥 ", 'threshold' => ' １０ '])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('materials', ['name' => '全角箱', 'stock' => 40, 'location' => '倉庫 奥', 'threshold' => 10]);
        $this->get(route('admin.materials.index'))->assertSeeInOrder(['全角箱', '40', '倉庫 奥', '10']);

        $material = Material::query()->where('name', '全角箱')->firstOrFail();
        $this->update($material, ['name' => '全角箱', 'stock' => '９９９９９', 'location' => '倉庫', 'threshold' => '０'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('materials', ['id' => $material->id, 'stock' => 99999, 'threshold' => 0]);
    }

    /**
     * 先頭が0の数字（半角・全角）は受け付けて、整数として保存する（「05」→5、「0」「00」→0）。追加も修正も同じ
     */
    #[Test]
    public function 先頭が0の数字は整数として保存できる(): void
    {
        $this->loginAsAdmin();

        $this->store(['name' => '先頭0の箱', 'stock' => '05', 'threshold' => '０５'])->assertSessionHasNoErrors()->assertSessionHas('list_status');
        $this->assertDatabaseHas('materials', ['name' => '先頭0の箱', 'stock' => 5, 'threshold' => 5]);

        $this->store(['name' => '0の箱', 'stock' => '00', 'threshold' => '0'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('materials', ['name' => '0の箱', 'stock' => 0, 'threshold' => 0]);

        $material = Material::query()->where('name', '先頭0の箱')->firstOrFail();
        $this->update($material, ['name' => '先頭0の箱', 'stock' => '０００７', 'threshold' => '0099999'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('materials', ['id' => $material->id, 'stock' => 7, 'threshold' => 99999]);

        // 先頭が0でも、上限を超える・負・小数・文字はエラーのまま
        $this->store(['name' => '誤りの箱', 'stock' => '0100000'])->assertSessionHasErrors(['stock' => '在庫数は99999以下で入力してください']);
        $this->store(['name' => '誤りの箱', 'stock' => '-05'])->assertSessionHasErrors(['stock' => '在庫数は0以上の整数で入力してください']);
        $this->store(['name' => '誤りの箱', 'threshold' => '01.5'])->assertSessionHasErrors(['threshold' => '基準値は0以上の整数で入力してください']);
        $this->store(['name' => '誤りの箱', 'threshold' => '0a'])->assertSessionHasErrors(['threshold' => '基準値は0以上の整数で入力してください']);
        $this->assertDatabaseMissing('materials', ['name' => '誤りの箱']);
    }

    /**
     * 修正で、IDの資材が存在しない（削除済み）ときは、ほかの項目の検証（資材名の重複・入力の誤り）より先に「資材が見つかりません。」で一覧に戻す（モーダルは開かず、入力も残さない）
     */
    #[Test]
    public function 存在しない資材の修正はほかの項目の検証より先に見つからない案内になる(): void
    {
        $this->loginAsAdmin();
        $other = Material::factory()->create(['name' => 'UAT箱B']);
        $gone = Material::factory()->create(['name' => 'UAT箱A']);
        $this->deleteMaterial($gone->id);

        // 削除済みの資材の修正で、ほかの資材と同じ資材名・入力の誤りを送っても、資材名の重複などのエラーにならない
        $this->update($gone, ['name' => 'UAT箱B', 'stock' => '-1', 'location' => ''])
            ->assertRedirect(route('admin.materials.index'))
            ->assertSessionHasErrors(['material' => '資材が見つかりません。'])
            ->assertSessionDoesntHaveErrors(['name', 'stock', 'location', 'threshold']);
        $this->update($gone, ['id' => '999999', 'name' => 'UAT箱B'])
            ->assertSessionHasErrors(['material' => '資材が見つかりません。'])
            ->assertSessionDoesntHaveErrors(['name']);

        $html = $this->get(route('admin.materials.index'))->assertOk()->getContent();
        $this->assertFalse($this->formState($html)['formOpen']);
        $this->assertStringNotContainsString('その資材名はすでに登録されています', $html);
        $this->assertDatabaseCount('materials', 1);
        $this->assertSame('UAT箱B', $other->fresh()->name);
    }

    /**
     * 入力の誤りで戻ると、モーダルが開いた状態で入力が残り、項目ごとに赤字のエラーが出る（追加）
     */
    #[Test]
    public function 追加の入力の誤りでは追加のモーダルが開いたまま入力が残る(): void
    {
        $this->loginAsAdmin();

        $this->store(['name' => 'UAT新箱', 'stock' => '-1', 'location' => '', 'threshold' => 'abc'])->assertRedirect(route('admin.materials.index'));
        $response = $this->get(route('admin.materials.index'))->assertOk();
        $html = $response->getContent();

        $state = $this->formState($html);
        $this->assertTrue($state['formOpen']);
        $this->assertSame('create', $state['mode']);
        $this->assertTrue($state['showErrors']);
        $this->assertSame('UAT新箱', $state['name']);
        $this->assertSame(['name' => false, 'stock' => true, 'location' => true, 'threshold' => true], $state['invalid']);
        $this->assertStringContainsString('value="UAT新箱"', $html);
        $this->assertStringContainsString('value="-1"', $html);
        $this->assertStringContainsString('value="abc"', $html);
        foreach (['在庫数は0以上の整数で入力してください', '置き場所を入力してください', '基準値は0以上の整数で入力してください'] as $message) {
            $this->assertMatchesRegularExpression('#<p id="material-[a-z]+-error" class="text-base text-danger"[^>]*>'.preg_quote($message, '#').'</p>#u', $html);
        }
        $this->assertStringNotContainsString('資材名を入力してください', $html);
        // 枠はエラーのある項目だけ赤
        $this->assertMatchesRegularExpression('#id="material-stock"#', $html);
        $this->assertSame(3, preg_match_all('#border-danger"\s+id="material-(stock|location|threshold)"#', $html));
        // 資材が見つからない案内は出ない
        $this->assertStringNotContainsString('資材が見つかりません。', $html);
        // aria-describedby は、エラーのある項目のエラー文の id だけを指す（基準値の補足は常に指す）
        $this->assertStringNotContainsString('aria-describedby="material-name-error"', $html);
        foreach (['stock', 'location'] as $field) {
            $this->assertStringContainsString('aria-describedby="material-'.$field.'-error"', $html);
        }
        $this->assertStringContainsString('aria-describedby="material-threshold-note material-threshold-error"', $html);
        $this->assertStringContainsString('x-init="if (formOpen) $nextTick(() => focusInitial())"', $html);
    }

    /**
     * 入力の誤りで戻ると、修正のモーダルが同じ資材（id）で開いたまま入力が残る
     */
    #[Test]
    public function 修正の入力の誤りでは修正のモーダルが開いたまま入力が残る(): void
    {
        $this->loginAsAdmin();
        $material = Material::factory()->create(['name' => 'UAT箱A']);

        $this->update($material, ['name' => '', 'stock' => '100000', 'location' => '残る置き場所', 'threshold' => '3'])
            ->assertRedirect(route('admin.materials.index'));
        $html = $this->get(route('admin.materials.index'))->assertOk()->getContent();

        $state = $this->formState($html);
        $this->assertTrue($state['formOpen']);
        $this->assertSame('edit', $state['mode']);
        $this->assertSame((string) $material->id, $state['id']);
        $this->assertStringContainsString('name="id" value="'.$material->id.'"', $html);
        $this->assertStringContainsString('value="残る置き場所"', $html);
        $this->assertStringContainsString('資材名を入力してください', $html);
        $this->assertStringContainsString('在庫数は99999以下で入力してください', $html);
        $this->assertSame('UAT箱A', $material->fresh()->name);
    }

    /**
     * 存在しない（削除済み）資材・IDの形が正しくない場合の修正は、何も変えずに一覧に戻って赤字の案内が出る（モーダルは開かない）
     */
    #[Test]
    public function 存在しない資材の修正は見つからない案内になる(): void
    {
        $this->loginAsAdmin();
        $other = Material::factory()->create(['name' => '残る資材', 'stock' => 10]);
        $gone = Material::factory()->create();
        $this->deleteMaterial($gone->id);

        foreach ([(string) $gone->id, '999999', 'abc', '0', '', ['1']] as $id) {
            $this->update($other, ['id' => $id, 'name' => '変更'])
                ->assertRedirect(route('admin.materials.index'))
                ->assertSessionMissing('list_status')
                ->assertSessionHasErrors(['material' => '資材が見つかりません。']);
        }

        $this->assertDatabaseCount('materials', 1);
        $this->assertSame('残る資材', $other->fresh()->name);
        $response = $this->get(route('admin.materials.index'))->assertOk();
        $response->assertSee('資材が見つかりません。')->assertSee('text-danger', false);
        $this->assertFalse($this->formState($response->getContent())['formOpen']);
    }

    /**
     * 削除すると物理削除になり、一覧に戻って緑の案内が出る。ほかの資材は残る
     */
    #[Test]
    public function 資材が物理削除され一覧に戻って案内が出る(): void
    {
        $this->loginAsAdmin();
        $target = Material::factory()->create(['name' => '削除する資材']);
        $other = Material::factory()->create(['name' => '残る資材']);

        $this->deleteMaterial($target->id)
            ->assertRedirect(route('admin.materials.index'))
            ->assertSessionHas('list_status', '資材を削除しました。')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('materials', ['id' => $target->id]);
        $this->assertDatabaseHas('materials', ['id' => $other->id]);
        $this->get(route('admin.materials.index'))->assertSee('資材を削除しました。')->assertSee('残る資材')->assertDontSee('削除する資材');
    }

    /**
     * 存在しない（削除済み）資材・IDの形が正しくない場合の削除は、何も変えずに一覧に戻って赤字の案内が出る
     */
    #[Test]
    public function 存在しない資材の削除は見つからない案内になる(): void
    {
        $this->loginAsAdmin();
        $other = Material::factory()->create(['name' => '残る資材']);
        $gone = Material::factory()->create();
        $this->deleteMaterial($gone->id)->assertSessionHas('list_status');

        foreach ([$gone->id, 999999, 'abc', 0, '', ['1']] as $id) {
            $this->deleteMaterial($id)
                ->assertRedirect(route('admin.materials.index'))
                ->assertSessionMissing('list_status')
                ->assertSessionHasErrors(['delete' => '資材が見つかりません。']);
        }

        $this->assertDatabaseCount('materials', 1);
        $this->assertDatabaseHas('materials', ['id' => $other->id]);
        $this->get(route('admin.materials.index'))->assertSee('資材が見つかりません。');
    }

    /**
     * 419（ページの有効期限切れ）のとき、追加・修正は一覧に戻ってモーダルが開き入力が残る。更新はされない
     */
    #[Test]
    public function トークンが古いときは追加と修正のモーダルを開いて入力を残す(): void
    {
        $this->loginAsAdmin();
        $material = Material::factory()->create(['name' => '元の資材']);
        $this->app['env'] = 'local';
        $index = route('admin.materials.index');

        $this->withSession(['_token' => 'valid-token'])
            ->post(route('admin.materials.store'), ['_token' => 'stale-token', 'name' => '残る名前', 'stock' => '12', 'location' => '残る場所', 'threshold' => '3'])
            ->assertRedirect($index)
            ->assertSessionHasErrors(['form' => ExpiredPageResponder::MATERIAL_STORE_MESSAGE]);
        $html = $this->get($index)->assertOk()->getContent();
        $state = $this->formState($html);
        $this->assertTrue($state['formOpen']);
        $this->assertSame('create', $state['mode']);
        $this->assertStringContainsString('<p class="mt-6 text-base text-danger" role="alert" x-show="showErrors">ページの有効期限が切れました。もう一度追加してください。</p>', $html);
        $this->assertStringContainsString('value="残る名前"', $html);
        $this->assertStringContainsString('value="12"', $html);
        $this->assertStringNotContainsString('stale-token', $html);

        $this->withSession(['_token' => 'valid-token'])
            ->post(route('admin.materials.update'), ['_token' => 'stale-token', 'id' => (string) $material->id, 'name' => '変えた名前', 'stock' => '7', 'location' => '残る場所', 'threshold' => '3'])
            ->assertRedirect($index)
            ->assertSessionHasErrors(['form' => ExpiredPageResponder::MATERIAL_UPDATE_MESSAGE]);
        $html = $this->get($index)->assertOk()->getContent();
        $state = $this->formState($html);
        $this->assertSame(['edit', (string) $material->id], [$state['mode'], $state['id']]);
        $this->assertStringContainsString('もう一度修正してください。</p>', $html);
        $this->assertStringContainsString('value="変えた名前"', $html);

        $this->assertDatabaseCount('materials', 1);
        $this->assertSame('元の資材', $material->fresh()->name);
    }

    /**
     * 419の戻り先は、ログインとIDの形で変わる。削除の419は一覧に戻って赤字の案内になる（モーダルは開かない）
     */
    #[Test]
    public function トークンが古いときの戻り先はログインとidの形で変わる(): void
    {
        $this->app['env'] = 'local';
        $stale = ['_token' => 'stale-token'];

        $this->withSession(['_token' => 'valid-token'])->post(route('admin.materials.store'), $stale)->assertRedirect(route('admin.login'));
        $this->withSession(['_token' => 'valid-token'])->post(route('admin.materials.update'), $stale + ['id' => '1'])->assertRedirect(route('admin.login'));
        $this->withSession(['_token' => 'valid-token'])->delete(route('admin.materials.delete'), $stale + ['id' => '1'])->assertRedirect(route('admin.login'));

        $this->loginAsAdmin();
        foreach ([[], ['id' => 'abc'], ['id' => '0'], ['id' => ['1']]] as $extra) {
            $this->withSession(['_token' => 'valid-token'])->post(route('admin.materials.update'), $stale + $extra)
                ->assertRedirect(route('admin.materials.index'))
                ->assertSessionHasErrors(['material' => ExpiredPageResponder::MATERIAL_UPDATE_MESSAGE]);
        }

        $material = Material::factory()->create();
        $this->withSession(['_token' => 'valid-token'])->delete(route('admin.materials.delete'), $stale + ['id' => (string) $material->id])
            ->assertRedirect(route('admin.materials.index'))
            ->assertSessionHasErrors(['delete' => ExpiredPageResponder::MATERIAL_DELETE_MESSAGE]);
        $this->assertDatabaseHas('materials', ['id' => $material->id]);

        $response = $this->get(route('admin.materials.index'))->assertOk();
        $response->assertSee(ExpiredPageResponder::MATERIAL_DELETE_MESSAGE);
        $this->assertFalse($this->formState($response->getContent())['formOpen']);
    }

    /**
     * 検証のあとに同じ資材名が登録された（同時に送られた）ときは、DBのユニーク制約の違反を資材名の重複のエラーとして返す
     */
    #[Test]
    public function 同時に送られて制約に違反したときも資材名の重複のエラーにする(): void
    {
        $this->loginAsAdmin();
        $material = Material::factory()->create(['name' => 'UAT箱A']);
        $other = Material::factory()->create(['name' => 'UAT箱B']);

        // 検証は通るが、DBのユニーク制約で止まる状況を作る（検証のあとに別のリクエストが登録した状況の代わり）
        Material::saving(function (Material $saving) use ($other): void {
            if ($saving->isDirty('name')) {
                $saving->name = $other->name;
            }
        });

        $this->update($material, ['name' => 'UAT箱C'])
            ->assertRedirect(route('admin.materials.index'))
            ->assertSessionHasErrors(['name' => 'その資材名はすでに登録されています']);
        $this->store(['name' => 'UAT箱D'])->assertSessionHasErrors(['name' => 'その資材名はすでに登録されています']);

        $this->assertSame('UAT箱A', $material->fresh()->name);
        $this->assertDatabaseCount('materials', 2);
    }
}
