<?php
// 設定ファイルを読み込み
require_once __DIR__ . '/config.php';

// PhpSpreadsheet の IOFactory クラスを使用
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

use function PHPSTORM_META\override;

function loadSql($filePath)
{
    $path = __DIR__ . '/sql/' . $filePath;
    if (!file_exists($path)) {
        throw new Exception("SQL file not found: " . $path);
    }
    return file_get_contents($path);
}

/**
 * 必要なディレクトリ（アップロード先）を作成する
 */
function ensureDirs()
{
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
}

/**
 * HTMLエスケープ関数
 * 出力時のXSS対策
 */
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * アップロードされたCSVファイルを保存する
 * @param string $key $_FILES配列のキー
 * @return string|null 保存先パス（失敗時はnull）
 */
function saveUpload(string $key): ?string
{
    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) return null;

    // ファイル名と拡張子のチェック
    $name = basename($_FILES[$key]['name']);
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext !== 'csv') return null;

    // 保存先ファイル名を生成（日時＋ランダム値）
    $dest = UPLOAD_DIR . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '_' . $name;

    // アップロードファイルを保存
    return move_uploaded_file($_FILES[$key]['tmp_name'], $dest) ? $dest : null;
}

/**
 * CSVファイルをUTF-8配列として読み込む（Shift-JIS想定）
 * - 可能なら iconv ストリームフィルタを使用（高速）
 * - フィルタが無い環境では全文読み→mb_convert_encoding でUTF-8化→行ごとに str_getcsv
 * - 1行目をヘッダにし、返却配列は【社員コード(1列目) => 行配列】の連想配列
 *   ※ 同一社員コードが複数行ある場合は、後勝ち（最後の行で上書き）です
 */
function loadCsv(string $path): array
{
    $rowsByEmp = [];
    if (!is_file($path)) return $rowsByEmp;

    // ---- iconv ストリームフィルタが使えるか確認
    $filters = array_map('strtolower', stream_get_filters());
    $filterName = null;
    if (in_array('convert.iconv.sjis-win/utf-8', $filters, true)) {
        $filterName = 'convert.iconv.SJIS-win/UTF-8';
    } elseif (in_array('convert.iconv.cp932/utf-8', $filters, true)) {
        $filterName = 'convert.iconv.CP932/UTF-8';
    }

    if ($filterName !== null) {
        // ====== (A) フィルタ使用パス ======
        $fh = @fopen($path, 'rb');
        if ($fh === false) return $rowsByEmp;
        stream_filter_append($fh, $filterName);

        // ヘッダ取得
        $header = fgetcsv($fh);
        if ($header === false) {
            fclose($fh);
            return $rowsByEmp;
        }

        // BOM除去・トリム・空ヘッダ補完
        if (isset($header[0])) $header[0] = preg_replace("/^\xEF\xBB\xBF/u", '', (string)$header[0]);
        foreach ($header as $i => $h) {
            $h = trim((string)$h);
            $header[$i] = ($h === '' ? 'col_' . ($i + 1) : $h);
        }
        $colCount = count($header);

        // データ行
        while (($r = fgetcsv($fh)) !== false) {
            // 列数ズレ補正
            if (count($r) < $colCount) $r = array_pad($r, $colCount, '');
            if (count($r) > $colCount) $r = array_slice($r, 0, $colCount);

            $assoc = array_combine($header, $r);
            // 1列目＝社員コード（必ず入る前提）
            $empCode = (string)$r[0];
            $empCode = trim($empCode);
            if ($empCode === '') continue; // 念のため空はスキップ
            $rowsByEmp[$empCode] = $assoc; // 同一キーは後勝ち
        }
        fclose($fh);
        return $rowsByEmp;
    }

    // ====== (B) フォールバック：全文読み→UTF-8変換→行パース ======
    $bin = file_get_contents($path);
    if ($bin === false) return $rowsByEmp;

    $head = substr($bin, 0, 4096);
    $enc  = mb_detect_encoding($head, ['SJIS-win', 'CP932', 'SJIS', 'UTF-8', 'EUC-JP', 'JIS'], true) ?: 'SJIS-win';
    $text = function_exists('mb_convert_encoding')
        ? mb_convert_encoding($bin, 'UTF-8', $enc)
        : iconv($enc, 'UTF-8//TRANSLIT', $bin);

    $lines = preg_split("/\r\n|\r|\n/", $text);
    if (!$lines) return $rowsByEmp;

    // ヘッダ
    $header = str_getcsv(array_shift($lines));
    if (!$header) return $rowsByEmp;

    if (isset($header[0])) $header[0] = preg_replace("/^\xEF\xBB\xBF/u", '', (string)$header[0]);
    foreach ($header as $i => $h) {
        $h = trim((string)$h);
        $header[$i] = ($h === '' ? 'col_' . ($i + 1) : $h);
    }
    $colCount = count($header);

    // データ行
    foreach ($lines as $line) {
        if ($line === '' || $line === null) continue;
        $r = str_getcsv($line);
        if ($r === null) continue;

        if (count($r) < $colCount) $r = array_pad($r, $colCount, '');
        if (count($r) > $colCount) $r = array_slice($r, 0, $colCount);

        $assoc = array_combine($header, $r);
        $empCode = (string)$r[0];
        $empCode = trim($empCode);
        if ($empCode === '') continue;
        $empCode = str_pad($empCode, 7, '0', STR_PAD_LEFT); // 7桁固定（空欄ゼロ埋め）
        $rowsByEmp[$empCode] = $assoc; // 同一キーは後勝ち
    }
    return $rowsByEmp;
}

/**
 * 時間文字列(HH:MM)を10進数の時間に変換
 * 例: "1:30" → 1.5
 */
function hhmmToDec(?string $s): float
{
    if (!$s) return 0.0;

    // HH:MM 形式
    if (preg_match('/^\s*(\d{1,3}):([0-5]\d)\s*$/', $s, $m)) {
        return (int)$m[1] + ((int)$m[2] / 60);
    }

    // 数値（カンマ区切り対応）
    if (is_numeric(str_replace(',', '.', $s))) {
        return (float)str_replace(',', '.', $s);
    }
    return 0.0;
}

/**
 * 指定キーのいずれかに値があれば時間換算して返す
 */
function valOr0(array $row, array $keys): float
{
    foreach ($keys as $k) {
        if (isset($row[$k]) && $row[$k] !== '') return hhmmToDec((string)$row[$k]);
    }
    return 0.0;
}

/**
 * 配列の中から最初に存在するキーを返す
 */
function firstKey(array $row, array $candidates): ?string
{
    foreach ($candidates as $k) {
        if (array_key_exists($k, $row)) return $k;
    }
    return null;
}

/** 列番号(1始まり)→A, B, ... */
function xlCol(int $n): string
{
    $s = '';
    while ($n > 0) {
        $m = ($n - 1) % 26;
        $s = chr(65 + $m) . $s;
        $n = intdiv($n - 1, 26);
    }
    return $s;
}
/** "1,234"や"1.5"→数値 */
function toNumber($v): float
{
    $t = str_replace(',', '', (string)$v);
    return is_numeric($t) ? (float)$t : 0.0;
}
/** 最初に見つかったキーの値（未定義→$default） */
function pickVal(array $row, array $keys, string $default = '0.0'): string
{
    foreach ($keys as $k) {
        if (isset($row[$k]) && $row[$k] !== '') return (string)$row[$k];
    }
    return $default;
}
/** 指定キー群の合計を文字列で返す */
function pickSum(array $row, array $keys): string
{
    $sum = 0.0;
    foreach ($keys as $k) {
        if (isset($row[$k]) && $row[$k] !== '') $sum += toNumber($row[$k]);
    }
    return (string)$sum;
}
/** 支給日を YYYY/MM/DD に整形 */
function normalizePayday(string $payday): string
{
    $payday = trim($payday);
    if ($payday === '') return '';
    $payday = str_replace(['年', '月', '.', '/'], ['-', '-', '-', '-'], $payday);
    $ts = strtotime($payday);
    return $ts ? date('Y/m/d', $ts) : $payday;
}
/** 両CSVの社員コード和集合（昇順） */
function unionEmployeeCodes(array $csv1ByEmp, array $csv2ByEmp): array
{
    $codes = array_unique(array_merge(array_keys($csv1ByEmp), array_keys($csv2ByEmp)));
    sort($codes, SORT_NATURAL);
    return $codes;
}

/**
 * 指定キー群から最初に見つかった値を「分/HH:MM想定」で 時間.分 形式にして返す
 * @param array  $row     行データ
 * @param array  $keys    探すキー群
 * @param string $default 値がなかった場合の返却
 * @param int    $decimals 小数点以下桁数
 */
function pickMinutesAsHourMinuteStr(array $row, array $keys, string $default = '0.0'): string
{
    foreach ($keys as $k) {
        if (isset($row[$k]) && $row[$k] == '0')
            return '0.00';
        if (isset($row[$k]) && $row[$k] !== '') {
            return minutesOrHhmmToHourMinuteStr((string)$row[$k]);
        }
    }
    return $default;
}

/**
 * 分(整数) or "HH:MM" を 時間.分 文字列へ
 * @param string $v 入力値（例: 13090）
 */
function minutesOrHhmmToHourMinuteStr(string $v): string
{
    // 前後の空白を削除
    $v = trim($v);

    // 空文字なら 0.00 を返す
    if ($v === '') return '0.00';

    // 数字のみの場合（例: "2530" 分）
    if (preg_match('/^\d+$/', $v)) {
        $total = (int)$v;
        $hours = intdiv($total, 60);   // 時間
        $minutes = $total % 60;        // 分
    }
    // "HH:MM" 形式の場合（例: "1:30"）
    elseif (preg_match('/^\s*(\d{1,3}):([0-5]\d)\s*$/', $v, $m)) {
        $hours = (int)$m[1];
        $minutes = (int)$m[2];
    }
    // 上記以外の形式はそのまま返す
    else {
        return $v;
    }

    // ゼロは常に "0.00"
    if ($hours === 0 && $minutes === 0) {
        return '0.00';
    }

    // HH.MM 形式（例: 42時間10分 → "42.10"）
    return sprintf('%d.%02d', $hours, $minutes);
}

/**
 * 社員コードごとに template.xls に1行ずつ書き込む
 *
 * @param array  $csvKintai  勤怠集計CSV
 * @param array  $csvKyuka   休暇取得CSV
 * @param string $payday     給与支給日
 * @param array  $employeesWithApplications 人事管理マスタ＋設定適用マスタの統合配列
 * @param int    $contractWorkMinutes       契約所定時間（分）
 * @param int    $flexStandardMinutes       フレックス基準時間（分）
 * @param array  $managementPositionCodes   管理職の職位コード配列
 * @param array  $halfHolidayByEmp          前有・後有を同日に取得した社員のリスト
 * @return string 生成された XLS ファイルのパス
 */
function buildXlsToTempByEmployee(
    array $csvKintai,
    array $csvKyuka,
    string $payday,
    array $employeesWithApplications,
    int $contractWorkMinutes,
    int $flexStandardMinutes,
    array $managementPositionCodes,
    array $halfHolidayByEmp
) {
    if (!is_file(TEMPLATE_XLS)) {
        throw new RuntimeException('template.xls が見つかりません。');
    }

    // Excelテンプレートをロード
    $spreadsheet = IOFactory::load(TEMPLATE_XLS);
    $sheet = $spreadsheet->getActiveSheet();
    $rowIndex = 10; // 書き込み開始行

    // 支給日の日付形式を YYYY/MM/DD 形式に変換
    $paydayFmt = normalizePayday($payday);

    // 勤怠集計CSV、休暇取得CSVの両方に存在する社員コードを抽出
    $employeeCodes = unionEmployeeCodes($csvKintai, $csvKyuka);

    foreach ($employeeCodes as $employeeCode) {
        $kintaiRow = $csvKintai[$employeeCode] ?? [];
        $kyukaRow      = $csvKyuka[$employeeCode] ?? [];
        $employeeCode  = str_pad((string)$employeeCode, 7, '0', STR_PAD_LEFT); // 社員番号

        $rowValues = [];

        // --- 各種判定フラグ ---
        $positionCode      = str_pad((string)$kintaiRow['職位コード'], 3, '0', STR_PAD_LEFT) ?? null;
        $isManagement      = in_array($positionCode, $managementPositionCodes, true); // 管理者
        $isFlex            = ($kintaiRow['雇用契約名称'] ?? '') === '社員FL'; // フレックス
        $patternName       = $employeesWithApplications[$employeeCode]['pattern_name'] ?? '';
        $isMonthly         = str_contains($patternName, '月給者'); // 月給者
        $isHourly          = str_contains($patternName, '時給者'); // 時給者
        $isChildCareWorker = str_contains($patternName, '育勤'); // 育児勤務者

        // 管理職以外の月給者＋時給者＋育児勤務者を割増対象とする
        // $isVariableWorker  = ((!$isManagement && $isMonthly) || $isHourly || $isChildCareWorker);

        $rowValues[] = 'F380';        // 1. お客様番号
        $rowValues[] = '001';         // 2. 給与会社番号
        $rowValues[] = 'PAY010';      // 3. 区分
        $rowValues[] = $paydayFmt;    // 4. 支給年月日
        $rowValues[] = 'P';           // 5. 処理種別
        $rowValues[] = '';            // 6. 処理種別分類
        $rowValues[] = $employeeCode; // 7. 社員番号

        // --- 8. 出勤日数 ---
        $rowValues[] = (int) round((float) pickVal($kintaiRow, ['出勤回数'], '0'), 0, PHP_ROUND_HALF_UP); // 四捨五入   

        // --- 9 有休（全休）---
        $rowValues[] = (string)((int)($kyukaRow['有給休暇(全休)'] ?? 0) + (int)($kyukaRow['ストック休暇(全休)'] ?? 0));

        // --- 10. 勤務時間 ---
        if ($isFlex) {
            // フレックス：休暇日数を基準時間から減算
            // 7時間55分減算対象の休暇日数を取得
            $holiday755cnt =
                (int)$kyukaRow['有給休暇(全休)']
                + (int)$kyukaRow['前期特別休暇(全休)【18】']
                + (int)$kyukaRow['後期特別休暇(全休)【19】']
                + (int)$kyukaRow['結婚休暇(全休)【20】']
                + (int)$kyukaRow['忌引休暇(全休)【22】']
                + (int)$kyukaRow['産休（有給）(全休)【23】']
                + (int)$kyukaRow['出勤停止(全休)【43】']
                + (int)$kyukaRow['公休(全休)【44】']
                + (int)$kyukaRow['労災欠勤(全休)【45】']
                + (int)$kyukaRow['災害休暇(全休)【46】']
                + (int)$kyukaRow['ストック休暇(全休)'];

            // 前有・後有の回数を取得
            $halfAm = (int)($kyukaRow['有給休暇(午前)'] ?? 0) + (int)($kyukaRow['ストック休暇(午前)'] ?? 0);
            $halfPm = (int)($kyukaRow['有給休暇(午後)'] ?? 0) + (int)($kyukaRow['ストック休暇(午後)'] ?? 0);

            // 前有・後有を同日に取った場合は全休扱いとする
            if ($halfAm >= 1 && $halfPm >= 1 && !empty($halfHolidayByEmp[$employeeCode])) {

                $bothHalfHolidayCount = (int)$halfHolidayByEmp[$employeeCode]['both_half_holiday_count']; // 前有・後有を同日に取得した回数
                $halfAm = $halfAm - $bothHalfHolidayCount; // 同日に取得した回数分「前有」から減算
                $halfPm = $halfPm - $bothHalfHolidayCount; // 同日に取得した回数分「後有」から減算
                $holiday755cnt = $holiday755cnt + $bothHalfHolidayCount; // 同日に取得した回数分「7時間55分」減算対象休暇日数に加算
            }

            // 3時間55分減算対象の休暇日数を取得
            $holiday355cnt = $halfAm + $halfPm;

            // 休暇取得日数を考慮したフレックス基準時間を算出
            $flexStdAdj    = $flexStandardMinutes - ($holiday755cnt * 475) - ($holiday355cnt * 235);

            // フレックス勤務者の「勤務時間」「残業時間」「減給時間」を算出
            $workMinutes   = (int)($kintaiRow['勤務時間'] ?? 0);
            if ($workMinutes >= $flexStdAdj) {
                $rowValues[]  = minutesOrHhmmToHourMinuteStr((string)$flexStdAdj); // フレックス勤務時間（[フレックス基準時間]とする）
                $flexOvertime = $workMinutes - $flexStdAdj;  // フレックス残業時間（[勤務時間] - [フレックス基準時間]とする）
                $flexPaycut   = '0.00';                      // フレックス減給時間（"0.00"とする）
            } else {
                $rowValues[]  = minutesOrHhmmToHourMinuteStr((string)$workMinutes); // フレックス勤務時間（[勤務時間]とする）
                $flexOvertime = '0.00'; // フレックス残業時間（"0.00"とする）
                $flexPaycut   = $flexStdAdj - $workMinutes;  // フレックス減給（[フレックス基準時間] - [勤務時間]とする）
            }
        } else {
            // フレックス以外：勤務時間 - 法定外 - 法定内
            $workMinutes = (int)($kintaiRow['勤務時間'] ?? 0)
                - (int)($kintaiRow['法定外残業時間(週40時間超除く)'] ?? 0)
                - (int)($kintaiRow['法定内残業時間(週40時間超除く)'] ?? 0);
            $rowValues[] = minutesOrHhmmToHourMinuteStr((string)$workMinutes);
        }

        // --- 11. 普通残業 ---
        if ($isFlex) {
            // フレックス：フレックス基準時間を超過している時間
            $rowValues[] = minutesOrHhmmToHourMinuteStr($flexOvertime);
        } elseif ($isManagement) {
            // 管理職："0.00" とする
            $rowValues[] = '0.00'; // 管理職
        } else {
            // フレックス・管理職以外："法定外残業時間"に対して割増分を加算する 
            $overtime = (int)($kintaiRow['法定外残業時間(週40時間超除く)'] ?? 0);
            if ($workMinutes > $contractWorkMinutes) {
                $overtime += $workMinutes - $contractWorkMinutes;
            }
            $rowValues[] = minutesOrHhmmToHourMinuteStr((string)$overtime);
        }

        // --- 12. 深夜時間 ---
        $rowValues[] = pickMinutesAsHourMinuteStr($kintaiRow, ['深夜時間'], '0.00');

        // --- 13. 深夜残業時間 ---
        if ($isFlex || $isManagement) {
            // フレックスまたは管理職："0.00" とする
            $rowValues[] = '0.00';
        } else {
            // フレックス以外：深夜残業時間
            $rowValues[] = pickMinutesAsHourMinuteStr($kintaiRow, ['深夜時間外時間'], '0.00');
        }

        // --- 14 法定内残業 ---
        if ($isFlex) {
            // フレックス："0.00" とする
            $rowValues[] = '0.00';
        } elseif ($isManagement) {
            // 管理職：法廷内残業時間に法定外残業時間を加算
            $sum = (int)($kintaiRow['法定外残業時間(週40時間超除く)'] ?? 0)
                + (int)($kintaiRow['法定内残業時間(週40時間超除く)'] ?? 0);
            $rowValues[] = minutesOrHhmmToHourMinuteStr((string)$sum);
        } else {
            // フレックス・管理職以外：法廷内残業時間
            $rowValues[] = pickMinutesAsHourMinuteStr($kintaiRow, ['法定内残業時間(週40時間超除く)'], '0.00');
        }

        // --- 15. 休日出勤 --- 
        $rowValues[] = '0.00';

        // --- 16. 減給時間 ---

        // 無欠無給の日数×7時間55分を計算
        $addMinutesUnpaidFullDays = 0;
        $addMinutesUnpaidFullDays = (int)pickVal($kyukaRow, ['無欠無給(全休)【29】'], '-') * 475;

        $rowValues[] = $isFlex ?
            minutesOrHhmmToHourMinuteStr($flexPaycut + $addMinutesUnpaidFullDays) : ($isManagement ?
                minutesOrHhmmToHourMinuteStr((int)$kintaiRow['減額対象時間'] + $addMinutesUnpaidFullDays) : minutesOrHhmmToHourMinuteStr((int)$kintaiRow['減額対象時間'] + $addMinutesUnpaidFullDays));

        // --- その他休暇・欠勤など ---
        $rowValues[] = '0'; // 17. 病欠100
        $rowValues[] = pickVal($kyukaRow, ['病欠150(全休)【38】'], '-'); // 18. 病欠150
        $rowValues[] = '0'; // 19. 認欠75
        $rowValues[] = pickVal($kintaiRow, ['振替休日日数'], '-'); // 20. 振休
        $rowValues[] = pickVal($kintaiRow, ['交替休日日数'], '-'); // 21. 交休
        $rowValues[] = pickVal($kyukaRow, ['休日(全休)【28】'], '-');  // 22. 休日
        $rowValues[] = (string)((int)($kyukaRow['有給休暇(午前)'] ?? 0) + (int)($kyukaRow['ストック休暇(午前)'] ?? 0));  // 23. 前有
        $rowValues[] = (string)((int)($kyukaRow['有給休暇(午後)'] ?? 0) + (int)($kyukaRow['ストック休暇(午後)'] ?? 0));  // 24. 後有
        $rowValues[] = pickVal($kyukaRow, ['前期特別休暇(全休)【18】'], '-');  // 25. 前期
        $rowValues[] = pickVal($kyukaRow, ['後期特別休暇(全休)【19】'], '-');  // 26. 後期
        $rowValues[] = pickVal($kyukaRow, ['公休(全休)【44】'], '-');  // 27. 公休
        $rowValues[] = pickVal($kyukaRow, ['前振(全休)【13】'], '-');  // 28. 前振
        $rowValues[] = pickVal($kyukaRow, ['忌引休暇(全休)【22】'], '-');  // 29. 忌引
        $rowValues[] = pickVal($kyukaRow, ['結婚休暇(全休)【20】'], '-');  // 30. 結婚
        $rowValues[] = pickVal($kyukaRow, ['産休（有給）(全休)【23】'], '-');  // 31. 産有
        $rowValues[] = pickVal($kyukaRow, ['産休（無給）(全休)【25】'], '-');  // 32. 産無
        $rowValues[] = pickVal($kyukaRow, ['育児休職(全休)【40】'], '-');   // 33. 育職
        $rowValues[] = pickVal($kyukaRow, ['介護休職(全休)【41】'], '-');   // 34. 介職
        $rowValues[] = pickVal($kyukaRow, ['無欠無給(全休)【29】'], '-');   // 35. 無欠（無給）
        $rowValues[] = pickVal($kyukaRow, ['介護・看護休暇（無給）(全休)【27】'], '-'); // 36. 看休（無給）
        $rowValues[] = pickVal($kyukaRow, ['生理休暇（無給）(全休)【26】'], '-'); // 37. 生休（無給）
        $rowValues[] = pickVal($kyukaRow, ['労災欠勤(全休)【45】'], '-');  // 38. 労災
        $rowValues[] = pickVal($kyukaRow, ['災害休暇(全休)【46】'], '-');  // 39. 労災
        $rowValues[] = '0'; // 40 育勤
        $rowValues[] = '0'; // 41 介勤

        // --- Excelへ書き込み ---
        foreach ($rowValues as $colIndex => $value) {
            $sheet->setCellValueExplicit(xlCol($colIndex + 2) . $rowIndex, $value, DataType::TYPE_STRING);
        }
        $rowIndex++;
    }

    $excelValues = [];

    // 出力ファイルを一時ディレクトリに保存
    $out = tempnam(sys_get_temp_dir(), 'xls_') . '.xls';
    IOFactory::createWriter($spreadsheet, 'Xls')->save($out);
    return [
        'out' => $out,
        'excelValues' => $excelValues
    ];
}


/**
 * 社員情報テーブルと設定適用テーブルを結合する
 */
function getApplicationSettingsToEmployees(array $empResult, array $applicationResult): array
{
    // 設定適用設定のベース項目を定義
    $baseKeys = ['position_code', 'section_code', 'employment_contract_code', 'work_place_code'];

    // tmm_applicationテーブルのマージ対象項目を定義
    $mergeKeys = [
        'application_code',
        'application_name',
        'work_setting_code',
        'work_setting_name',
        'work_setting_abbr',
        'schedule_code',
        'schedule_name',
        'schedule_abbr',
        'pattern_name',
        'paid_holiday_code',
        'paid_holiday_name',
        'paid_holiday_abbr'
    ];

    // 基本情報のマッチング条件セットを定義
    $matchConditions = [
        ['position_code', 'section_code', 'employment_contract_code', 'work_place_code'],
        ['position_code', 'section_code', 'employment_contract_code'],
        ['position_code', 'section_code'],
        ['position_code'],
        ['section_code', 'employment_contract_code', 'work_place_code'],
        ['section_code', 'employment_contract_code'],
        ['section_code'],
        ['employment_contract_code', 'work_place_code'],
        ['employment_contract_code'],
        ['work_place_code'],
    ];

    $employeesWithApplications = [];

    foreach ($empResult as $emp) {
        $employeeWithApplication = $emp;

        // 1. personal_idのマッチングを確認
        $personalMatch = null;
        foreach ($applicationResult as $app) {
            //設定適用区分が1の設定適用データを対象とする
            if ($app['application_type'] == 1) {
                if (!empty($app['personal_ids']) && strpos($app['personal_ids'], $emp['personal_id']) !== false) {
                    $personalMatch = $app;
                    break;
                }
            }
        }

        // personal_idがマッチングする場合
        if ($personalMatch !== null) {
            foreach ($mergeKeys as $key) {
                // tmm_applicationテーブルの指定項目をマージ
                $employeeWithApplication[$key] = $personalMatch[$key] ?? null;
            }
        } else {
            // 2. 基本情報のマッチングセット条件に合致するか確認
            $attributeMatch = null;

            foreach ($matchConditions as $keys) {
                foreach ($applicationResult as $app) {
                    //設定適用区分が0の設定適用データを対象とする
                    if ($app['application_type'] == 0) {
                        $match = true;

                        // 条件項目すべてが一致するかどうかを確認
                        foreach ($keys as $key) {
                            $empVal = $emp[$key] ?? null;
                            $appVal = $app[$key] ?? null;

                            // 値が異なる、または両方nullならマッチしない
                            if ($empVal !== $appVal || ($empVal === "" && $appVal === "")) {
                                $match = false;
                                break;
                            }
                        }

                        if ($match) {
                            // 条件に含まれていないベース項目に値があればマッチと判定しない
                            foreach ($baseKeys as $baseKey) {
                                if (!in_array($baseKey, $keys, true)) {
                                    if (!empty($app[$baseKey])) {
                                        $match = false;
                                        break;
                                    }
                                }
                            }
                        }

                        // すべての条件に合致＋不要な項目が空ならマッチ確定
                        if ($match) {
                            $attributeMatch = $app;
                            break 2;
                        }
                    }
                }
            }

            // 3. すべての条件に一致しなかった場合、設定情報の項目がすべて空欄のものを適用
            if ($attributeMatch === null) {
                foreach ($applicationResult as $app) {
                    //設定適用区分が0の設定適用データを対象とする
                    if ($app['application_type'] == 0) {

                        $allEmpty = true;
                        foreach ($baseKeys as $key) {
                            if (!empty($app[$key])) {
                                $allEmpty = false;
                                break;
                            }
                        }
                        if ($allEmpty) {
                            $attributeMatch = $app;
                            break;
                        }
                    }
                }
            }

            foreach ($mergeKeys as $key) {
                // tmm_applicationテーブルの指定項目をマージ
                $employeeWithApplication[$key] = $attributeMatch[$key] ?? null;
            }
        }

        $employeesWithApplications[] = $employeeWithApplication;
    }

    return $employeesWithApplications;
}
