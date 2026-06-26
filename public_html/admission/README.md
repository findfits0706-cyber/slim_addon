# Find Pilates 入会受付フォーム

Find Pilates のWeb入会受付フォームです。  
利用形態・希望種別・利用開始希望日・店頭での入会手続き希望日・健康確認・規約同意・顔写真登録を行い、確認画面を経て、クラブ宛メールと本人控えメールを送信します。

---

## 1. 設置場所

以下の構成で設置します。

```text
public_html/
└─ admission/
   ├─ index.php
   ├─ confirm.php
   ├─ send.php
   ├─ inc/
   │  ├─ config.php
   │  └─ functions.php
   ├─ css/
   │  └─ style.css
   ├─ js/
   │  └─ app.js
   └─ tmp/
      └─ .htaccess