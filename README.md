# ShioyaFarm
## .env変更部分
 ```
   APP_NAME=プロジェクト名

   APP_TIMEZONE=Asia/Tokyo

   APP_LOCALE=ja

   APP_FALLBACK_LOCALE=ja

   APP_FAKER_LOCALE=ja_JP

   DB_CONNECTION=mysql

   DB_HOST=mysql

   DB_PORT=3306

   DB_DATABASE=任意のデータベース名

   DB_USERNAME=任意のユーザー名

   DB_PASSWORD=任意のパスワード
   ```
> [!Warning]
> compose.yamlのMYSQL_DATABASE,MYSQL_USERNAME,MYSQL_PASSWORDと合わせること

## 実装の範囲

ポートフォリオ用のプロジェクトのため、設計書（`docs/`）のうち次の内容は意図して実装していません。

- 商品の販売時期（`docs/02-initial-settings.md`「取扱商品」）による販売・予約の制限。全商品を通年で購入・予約できます。詰め合わせセットの「発送は9月中」は、画面の文言として表示するだけです。
