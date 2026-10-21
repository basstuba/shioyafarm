# テーブル仕様書

ER図は [ER図](06-er-diagram.md) を参照。

## users（ユーザーデータ）

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| last_name | varchar(255) | | | ○ | |
| first_name | varchar(255) | | | ○ | |
| prefecture | varchar(255) | | | ○ | |
| city | varchar(255) | | | ○ | |
| tell | varchar(15) | | | ○ | |
| email | varchar(30) | | | ○ | |
| password | varchar(255) | | | ○ | |
| stripe_customer_id | varchar(255) | | ○ | | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |

## admins（管理者データ）

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| name | varchar(255) | | | ○ | |
| email | varchar(30) | | | ○ | |
| password | varchar(255) | | | ○ | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |

## items（掲載商品データ）

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| name | varchar(255) | | | ○ | |
| weight | integer | | | ○ | |
| price | integer | | | ○ | |
| img_url | varchar(255) | | | ○ | |
| detail | text | | | ○ | |
| recommend | boolean | | | ○ | |
| is_reserved_only | boolean | | | ○ | |
| reservation_limit | integer | | | | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |

## notices（お知らせ内容データ）

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| title | varchar(255) | | | ○ | |
| text | text | | | ○ | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |

## inquiries（お問い合わせデータ）

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| last_name | varchar(255) | | | ○ | |
| first_name | varchar(255) | | | ○ | |
| email | varchar(30) | | | ○ | |
| detail | text | | | ○ | |
| addressed | boolean | | | ○ | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |

## reservations（予約データ）

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| user_id | bigint unsigned | | | ○ | users(id) |
| item_id | bigint unsigned | | | ○ | items(id) |
| delivery_name | varchar(255) | | | ○ | |
| delivery_prefecture | varchar(255) | | | ○ | |
| delivery_city | varchar(255) | | | ○ | |
| delivery_tell | varchar(15) | | | ○ | |
| quantity | integer | | | ○ | |
| price | integer | | | ○ | |
| subtotal | integer | | | ○ | |
| payment_method | varchar(25) | | | ○ | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |

## purchase_histories（購入履歴データ）

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| user_id | bigint unsigned | | | ○ | users(id) |
| delivery_name | varchar(255) | | | ○ | |
| delivery_prefecture | varchar(255) | | | ○ | |
| delivery_city | varchar(255) | | | ○ | |
| delivery_tell | varchar(15) | | | ○ | |
| payment_method | varchar(25) | | | ○ | |
| shipping_fee | integer | | | ○ | |
| cod_fee | integer | | | ○ | |
| total_price | integer | | | ○ | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |

## purchase_history_items（購入商品データ）

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| purchase_history_id | bigint unsigned | | | ○ | purchase_histories(id) |
| item_id | bigint unsigned | | | ○ | items(id) |
| quantity | integer | | | ○ | |
| price | integer | | | ○ | |
| subtotal | integer | | | ○ | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |

## purchase_bank_transfers（銀行振込の振込先データ）

銀行振込（customer_balance）の注文だけ、購入履歴1件につき1件作る。マイページの購入履歴と注文確認メールで振込先を表示するために保存する。

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| purchase_history_id | bigint unsigned | | ○ | ○ | purchase_histories(id) |
| stripe_payment_intent_id | varchar(255) | | ○ | ○ | |
| bank_name | varchar(255) | | | ○ | |
| branch_name | varchar(255) | | | ○ | |
| account_type | varchar(25) | | | ○ | |
| account_number | varchar(25) | | | ○ | |
| account_holder_name | varchar(255) | | | ○ | |
| amount | integer | | | ○ | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |

## delivery_addresses（配送先住所データ）

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| user_id | bigint unsigned | | | ○ | users(id) |
| delivery_prefecture | varchar(255) | | | ○ | |
| delivery_city | varchar(255) | | | ○ | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |

## areas（セレクトボックス用都道府県データ）

| カラム名 | 型 | PRIMARY KEY | UNIQUE KEY | NOT NULL | FOREIGN KEY |
|---|---|:---:|:---:|:---:|---|
| id | bigint unsigned | ○ | | | |
| prefecture | varchar(255) | | | ○ | |
| created_at | timestamp | | | | |
| updated_at | timestamp | | | | |
