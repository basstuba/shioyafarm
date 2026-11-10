<?php

namespace App\Http\Requests;

use App\Services\ItemInput;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Override;

/**
 * 管理画面の掲載商品の登録の入力
 *
 * 同じ商品名・重さの重複と、予約済みの数より小さい予約可能数は、保存の処理（AdminItemService）で判定する。
 * 予約可能数は、「予約限定」で「限定」のときだけ検証する（通常のときは送られてきても捨てる）。
 */
class ItemStoreRequest extends FormRequest
{
    /** 商品画像の大きさの上限（KB。10MB。保存するときに縮小する） */
    public const int IMAGE_MAX_KB = 10240;

    /** 商品画像を選ぶ画面の案内（ブラウザで形式・大きさが合わなかったとき） */
    public const string IMAGE_HINT = 'jpeg・png・webp の10MB以下の画像を選んでください';

    /** 商品金額の上限（円） */
    public const int PRICE_MAX = 100000;

    /** 商品紹介文の文字数の上限 */
    public const int DETAIL_MAX = 1000;

    /** 予約可能数の上限（ユーザー決定：1〜999） */
    public const int RESERVATION_LIMIT_MAX = 999;

    /** 予約可能数：上限なし */
    public const string LIMIT_UNLIMITED = 'unlimited';

    /** 予約可能数：上限あり */
    public const string LIMIT_LIMITED = 'limited';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * 商品金額・予約可能数は、全角数字を半角にし、前後の空白（全角を含む）を除いてから検証する
     */
    #[Override]
    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['price', 'reservation_limit'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $normalized[$field] = trim(mb_convert_kana($value, 'ns'));
            }
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'weight' => ['required', 'in:2,5,10'],
            'price' => ['required', 'integer', 'min:1', 'max:'.self::PRICE_MAX],
            'image' => $this->imageRules(),
            'detail' => ['required', 'string', 'max:'.self::DETAIL_MAX],
            'recommend' => ['required', 'boolean'],
            'is_reserved_only' => ['required', 'boolean'],
            ...$this->reservationRules(),
        ];
    }

    /**
     * 商品画像の規則（登録では必須）
     *
     * @return list<string>
     */
    protected function imageRules(): array
    {
        return ['required', ...$this->imageFormatRules()];
    }

    /**
     * 商品画像の形式・大きさの規則（登録・修正で共通）
     *
     * @return list<string>
     */
    protected function imageFormatRules(): array
    {
        return ['file', 'mimes:jpeg,png,webp', 'max:'.self::IMAGE_MAX_KB];
    }

    /**
     * 予約可能数の規則（予約限定で「限定」のときだけ。上限ありのときだけ数を検証する）
     *
     * @return array<string, array<mixed>>
     */
    private function reservationRules(): array
    {
        if (! $this->boolean('is_reserved_only')) {
            return [];
        }

        $rules = ['reservation_limit_type' => ['required', Rule::in([self::LIMIT_UNLIMITED, self::LIMIT_LIMITED])]];

        if ($this->input('reservation_limit_type') === self::LIMIT_LIMITED) {
            $rules['reservation_limit'] = ['required', 'integer', 'min:1', 'max:'.self::RESERVATION_LIMIT_MAX];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function attributes(): array
    {
        return [
            'name' => '商品名',
            'weight' => '商品重量',
            'price' => '商品金額',
            'image' => '商品画像',
            'detail' => '商品紹介文',
            'recommend' => 'おすすめ商品',
            'is_reserved_only' => '予約限定',
            'reservation_limit_type' => '予約可能数',
            'reservation_limit' => '予約可能数',
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'name.required' => '商品名を入力してください',
            'name.string' => '商品名は文字で入力してください',
            'name.max' => '商品名は255文字以内で入力してください',
            'weight.required' => '商品重量を選択してください',
            'weight.in' => '商品重量を選択してください',
            'price.required' => '商品金額を入力してください',
            'price.integer' => '商品金額は整数で入力してください',
            'price.min' => '商品金額は1円以上で入力してください',
            'price.max' => '商品金額は'.number_format(self::PRICE_MAX).'円以下で入力してください',
            'image.required' => '商品画像を選択してください',
            'image.file' => '商品画像を選択してください',
            'image.uploaded' => '商品画像のアップロードに失敗しました。10MB以下の画像を選んでください',
            'image.mimes' => '商品画像はjpeg・png・webpのいずれかの形式にしてください',
            'image.max' => '商品画像は10MB以下にしてください',
            'detail.required' => '商品紹介文を入力してください',
            'detail.string' => '商品紹介文は文章で入力してください',
            'detail.max' => '商品紹介文は'.number_format(self::DETAIL_MAX).'文字以内で入力してください',
            'recommend.required' => 'おすすめ商品を選択してください',
            'recommend.boolean' => 'おすすめ商品を選択してください',
            'is_reserved_only.required' => '予約限定を選択してください',
            'is_reserved_only.boolean' => '予約限定を選択してください',
            'reservation_limit_type.required' => '予約可能数の上限の有無を選択してください',
            'reservation_limit_type.in' => '予約可能数の上限の有無を選択してください',
            'reservation_limit.required' => '予約可能数を入力してください',
            'reservation_limit.integer' => '予約可能数は整数で入力してください',
            'reservation_limit.min' => '予約可能数は1以上で入力してください',
            'reservation_limit.max' => '予約可能数は'.number_format(self::RESERVATION_LIMIT_MAX).'以下で入力してください',
        ];
    }

    /**
     * 検証済みの入力を、保存の処理に渡す形にする（予約可能数は、予約限定で上限ありのときだけ数、それ以外は null）
     */
    public function toInput(): ItemInput
    {
        $validated = $this->validated();
        $reservedOnly = $this->boolean('is_reserved_only');
        $limited = $reservedOnly && ($validated['reservation_limit_type'] ?? null) === self::LIMIT_LIMITED;
        $image = $this->file('image');

        return new ItemInput(
            name: $this->string('name')->toString(),
            weight: $this->integer('weight'),
            price: $this->integer('price'),
            detail: $this->string('detail')->toString(),
            recommend: $this->boolean('recommend'),
            isReservedOnly: $reservedOnly,
            reservationLimit: $limited ? $this->integer('reservation_limit') : null,
            image: $image instanceof UploadedFile ? $image : null,
        );
    }
}
