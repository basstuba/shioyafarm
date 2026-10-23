<x-mail::message>
{{ $recipientName }} 様

このたびは しおや農園 をご利用いただき、ありがとうございます。<br>
以下の内容でご予約を承りました。

<x-mail::panel>
<strong>■ ご予約内容</strong><br>
予約番号：{{ $orderNumber }}<br>
ご予約日時：{{ $orderedAt }}<br>
お支払方法：{{ $paymentLabel }}

{{ $itemName }} × {{ $quantity }}　　{{ $subtotal }}

商品小計　　{{ $subtotal }}<br>
送料　　　　{{ $shippingFee }}<br>
<strong>合計　　　　{{ $total }}</strong>
</x-mail::panel>

商品は9月より、準備が整い次第順次発送いたします。

@include('mail.partials.delivery')

@include('mail.partials.bank-transfer')

@include('mail.partials.closing', ['historyGuide' => 'ご予約の内容は、マイページの予約履歴でもご確認いただけます。'])
</x-mail::message>
