<?php

namespace App\Services\Payment;

use RuntimeException;

/**
 * 決済サービス（Stripe）との通信や設定に失敗したときの例外
 */
class PaymentGatewayException extends RuntimeException {}
