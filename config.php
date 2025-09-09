<?php
// アップロード保存先
const UPLOAD_DIR   = __DIR__ . '/storage';
const TEMPLATE_XLS = __DIR__ . '/template/template.xls';

// PostgreSQL接続情報
const PG_DSN  = 'pgsql:host=localhost;port=5432;dbname=YOUR_DB;';
const PG_USER = 'YOUR_USER';
const PG_PASS = 'YOUR_PASSWORD';

// Composer
require __DIR__ . '/vendor/autoload.php';