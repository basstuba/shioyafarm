{{-- 未ログインの人への会員登録の案内（文言と「会員登録」ボタン）。配置や余白は呼び出し側で決める --}}
<p {{ $attributes->class(['text-sm text-primary md:text-base']) }}>※ご購入の際は会員登録と<wbr>ログインをお願いします。</p>
<x-outline-button :href="route('register')">会員登録</x-outline-button>
