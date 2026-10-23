@if ($bankTransfer !== null)
<x-mail::panel>
<strong>■ お振込先</strong><br>
@if ($bankTransfer['paid'])
お客様の残高からお支払いが完了しています。
@else
銀行名：{{ $bankTransfer['bankName'] }}<br>
支店名：{{ $bankTransfer['branchName'] }}<br>
口座種別：{{ $bankTransfer['accountType'] }}<br>
口座番号：{{ $bankTransfer['accountNumber'] }}<br>
口座名義：{{ $bankTransfer['accountHolderName'] }}<br>
お振込金額：{{ $bankTransfer['amount'] }}

※ お客様専用の口座です。ご入金を確認してから発送いたします。
@endif
</x-mail::panel>
@endif
