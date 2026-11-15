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

## 画像について

サイトで使用している写真・アイコンは、次のサイトのフリー素材です。配布元の規約で素材の再配布が禁止されているため、このリポジトリには含めていません（ロゴは自作のため含めています）。

- 写真：[写真AC](https://www.photo-ac.com/)、[Pixabay](https://pixabay.com/ja/)
- アイコン：[ICOOON MONO](https://icooon-mono.com/)、[icon-rainbow](https://icon-rainbow.com/)

clone して動かす場合、画像は表示されません。表示するには、次のファイル名で任意の画像（WebP）を置いてください。

- `src/storage/app/public/image/icon/`：`bank-icon.webp`、`box-icon.webp`、`cart-header-icon.webp`、`credit-card-icon.webp`、`id-card-header-icon.webp`、`login-header-icon.webp`、`logout-icon.webp`、`notice-header-icon.webp`、`team-header-icon.webp`、`white-dustbin-icon.webp`
- `src/storage/app/public/image/product/`：`carrots-card.webp`、`onions-card.webp`、`potato-card.webp`、`vegetables-set-card.webp`
- `src/storage/app/public/image/tool/`：`admin-login-page-back-image.webp`、`farmer-introduction-photo.webp`、`fv-agriculture-photo.webp`、`fv-maine-photo.webp`、`fv-onion-photo.webp`、`fv-potatoes-photo.webp`、`inquiry-thanks-page-back-image.webp`、`login-page-back-image.webp`、`my-page-fv-flowers-photo.webp`、`online-fv-family-photo.webp`、`thanks-page-photo.webp`

## 著作権・ライセンス

© 2026 basstuba All rights reserved.

このリポジトリはポートフォリオとして閲覧用に公開しています。コード・文章・ロゴ画像の無断での複製・転載・再利用はお断りします。詳しくは [LICENSE](LICENSE) をご覧ください。
