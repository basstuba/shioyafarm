<x-mail::message>
{{ $messageBody }}
@if ($quote !== null)

<x-mail::panel>
<strong>■ いただいたお問い合わせ</strong><br>
お問い合わせ日：{{ $quote['date'] }}<br>
{{ $quote['detail'] }}
</x-mail::panel>
@endif
</x-mail::message>
