<x-mail::message>
{{ $recipientName }} 様

このたびは しおや農園 の会員にご登録いただき、ありがとうございます。<br>
会員登録が完了しました。

<x-mail::panel>
<strong>■ ご登録内容</strong><br>
お名前：{{ $recipientName }}<br>
メールアドレス：{{ $email }}
</x-mail::panel>

ご登録の内容の変更や退会は、マイページから行えます。<br>
下のボタンから、マイページを開けます。

<x-mail::button :url="$myPageUrl">
マイページを開く
</x-mail::button>

このメールにお心当たりがない場合は、お手数ですが、下記のお問い合わせフォームからご連絡ください。

このメールは送信専用です。ご不明な点は、下記のお問い合わせフォームからご連絡ください。<br>
<a href="{{ $contactUrl }}">{{ $contactUrl }}</a>

{{ $senderName }}<br>
{{ $senderAddress }}
</x-mail::message>
