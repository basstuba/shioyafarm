# ER図

![ER図](images/er-diagram.png)

## Mermaid版

このMermaid版が元の図で、上の画像（`images/er-diagram.png`）はここから生成したものです。カラムの詳細は [テーブル仕様書](05-table-specification.md) を参照してください。自分たちで作ったテーブルだけを載せ、Laravel 標準のテーブル（`password_reset_tokens`・`sessions`・`cache`・`jobs` など）は載せません。

テーブルを変えたときは、下のMermaidを直してから、画像を作り直します（mermaid-cli はプロジェクトには入れず、`npx` で一時的に使う。一時ファイルはリポジトリの外に置く）。画像の作り直し（`npx`）は、メインセッションが確認を経て行う。エージェントは Mermaid の修正までで止まり、画像の更新が必要だと報告する。

```bash
# docs/06-er-diagram.md の mermaid ブロックの中身（コードフェンスは除く）を /tmp/er.mmd に保存してから実行
echo '{"args":["--no-sandbox"]}' > /tmp/puppeteer.json
npx --yes @mermaid-js/mermaid-cli -i /tmp/er.mmd -o docs/images/er-diagram.png -b white -s 3 -p /tmp/puppeteer.json
```

```mermaid
erDiagram
    users ||--o{ delivery_addresses : "has"
    users ||--o{ reservations : "makes"
    users ||--o{ purchase_histories : "makes"
    items ||--o{ reservations : "is reserved in"
    items ||--o{ purchase_history_items : "is purchased in"
    purchase_histories ||--o{ purchase_history_items : "contains"
    purchase_histories ||--o| purchase_bank_transfers : "has"
    reservations ||--o| reservation_bank_transfers : "has"

    users {
        bigint id PK
        varchar last_name
        varchar first_name
        varchar prefecture
        varchar city
        varchar tell
        varchar email
        varchar password
        varchar stripe_customer_id
        text two_factor_secret
        text two_factor_recovery_codes
        timestamp two_factor_confirmed_at
        varchar remember_token
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    delivery_addresses {
        bigint id PK
        bigint user_id FK
        varchar delivery_prefecture
        varchar delivery_city
        timestamp created_at
        timestamp updated_at
    }

    reservations {
        bigint id PK
        bigint user_id FK
        bigint item_id FK
        varchar delivery_name
        varchar delivery_prefecture
        varchar delivery_city
        varchar delivery_tell
        int quantity
        int price
        int subtotal
        int shipping_fee
        int total_price
        varchar payment_method
        boolean shipped
        timestamp canceled_at
        timestamp created_at
        timestamp updated_at
    }

    reservation_bank_transfers {
        bigint id PK
        bigint reservation_id FK
        varchar stripe_payment_intent_id
        varchar bank_name
        varchar branch_name
        varchar account_type
        varchar account_number
        varchar account_holder_name
        int amount
        timestamp created_at
        timestamp updated_at
    }

    purchase_histories {
        bigint id PK
        bigint user_id FK
        varchar delivery_name
        varchar delivery_prefecture
        varchar delivery_city
        varchar delivery_tell
        varchar payment_method
        int shipping_fee
        int cod_fee
        int total_price
        boolean shipped
        timestamp canceled_at
        timestamp created_at
        timestamp updated_at
    }

    purchase_bank_transfers {
        bigint id PK
        bigint purchase_history_id FK
        varchar stripe_payment_intent_id
        varchar bank_name
        varchar branch_name
        varchar account_type
        varchar account_number
        varchar account_holder_name
        int amount
        timestamp created_at
        timestamp updated_at
    }

    purchase_history_items {
        bigint id PK
        bigint purchase_history_id FK
        bigint item_id FK
        int quantity
        int price
        int subtotal
        timestamp created_at
        timestamp updated_at
    }

    items {
        bigint id PK
        varchar name
        int weight
        int price
        varchar img_url
        text detail
        boolean recommend
        boolean is_reserved_only
        int reservation_limit
        timestamp created_at
        timestamp updated_at
    }

    admins {
        bigint id PK
        varchar name
        varchar email
        varchar password
        timestamp created_at
        timestamp updated_at
    }

    admin_password_reset_tokens {
        varchar email PK
        varchar token
        timestamp created_at
    }

    notices {
        bigint id PK
        varchar title
        text text
        timestamp created_at
        timestamp updated_at
    }

    inquiries {
        bigint id PK
        varchar last_name
        varchar first_name
        varchar email
        text detail
        boolean addressed
        timestamp created_at
        timestamp updated_at
    }

    areas {
        bigint id PK
        varchar prefecture
        timestamp created_at
        timestamp updated_at
    }
```
