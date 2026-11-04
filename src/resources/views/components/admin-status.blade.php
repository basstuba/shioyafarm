{{-- 管理画面の案内（flash の status。ログアウトの有効期限切れなど）。幅や余白は呼び出し側の class で渡す --}}
<p {{ $attributes->class('rounded-[10px] border border-line-strong bg-surface px-4 py-3 text-base text-ink') }} role="status">{{ $slot }}</p>
