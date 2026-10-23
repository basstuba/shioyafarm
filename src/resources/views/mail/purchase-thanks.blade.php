<x-mail::message>
{{ $recipientName }} 様

このたびは しおや農園 をご利用いただき、ありがとうございます。<br>
以下の内容でご注文を承りました。

<x-mail::panel>
<strong>■ ご注文内容</strong><br>
注文番号：{{ $orderNumber }}<br>
ご注文日時：{{ $orderedAt }}<br>
お支払方法：{{ $paymentLabel }}

{{ $itemLines }}

商品小計　　{{ $subtotal }}<br>
送料　　　　{{ $shippingFee }}<br>
@if ($codFee !== null)
代引手数料　{{ $codFee }}<br>
@endif
<strong>合計　　　　{{ $total }}</strong>
</x-mail::panel>

@include('mail.partials.delivery')

@include('mail.partials.bank-transfer')

@include('mail.partials.closing', ['historyGuide' => 'ご注文の内容は、マイページの購入履歴でもご確認いただけます。'])
</x-mail::message>
