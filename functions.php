<?php
// 設定ファイルを読み込み
require_once __DIR__ . '/config.php';

// PhpSpreadsheet の IOFactory クラスを使用
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

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
        if (isset($row[$k]) && $row[$k] !== '') {
            return minutesOrHhmmToHourMinuteStr((string)$row[$k]);
        }
    }
    return $default;
}

/**
 * 分(整数) or "HH:MM" を 時間.分 文字列へ
 * @param string $v 入力値（例: 13090, "1:30"）
 * @param int    $decimals 小数点以下桁数
 */
function minutesOrHhmmToHourMinuteStr(string $v): string
{
    // 前後の空白を削除
    $v = trim($v);

    // 空文字なら 0.0 を返す
    if ($v === '') return '0.0';

    // 数字のみの場合（例: "2530" 分）
    if (preg_match('/^\d+$/', $v)) {
        // 時間部分：60分で割った整数部分
        $hours = intdiv((int)$v, 60);
        // 分部分：60で割った余り
        $minutes = (int)$v % 60;
    }
    // "HH:MM" 形式の場合（例: "1:30"）
    elseif (preg_match('/^\s*(\d{1,3}):([0-5]\d)\s*$/', $v, $m)) {
        $hours = (int)$m[1];   // HH 部分
        $minutes = (int)$m[2]; // MM 部分
    }
    // 上記以外の形式の場合はそのまま返す
    else {
        return $v; // 例外値や文字列のまま返す
    }

    // HH.MM 形式で返す（例: 42時間10分 → "42.10"）
    return sprintf('%d.%02d', $hours, $minutes);
}


/**
 * 社員コードごとに template.xls に1行ずつ書き込む（開始行=10）
 * @param array  $csvKintai  勤怠集計CSV（社員コード => 行配列）
 * @param array  $csvKyuka   休暇取得CSV（社員コード => 行配列）
 * @param string $payday     給与支給日（例: 2025-07-31）
 * @param array $employeesWithApplications     人事管理マスタ・設定適用マスタを統合した連想配列
 * @param int $contractWorkMinutes     契約所定時間
 * @param int $flexStandardMinutes     フレックス基準時間
 * @param array $managementPositionCodes 管理職の職位コード配列
 * @return string 生成XLSパス（一時ファイル）
 */
function buildXlsToTempByEmployee(
    array $csvKintai,
    array $csvKyuka,
    string $payday,
    array $employeesWithApplications,
    int $contractWorkMinutes,
    int $flexStandardMinutes,   
    array $managementPositionCodes
): string {
    if (!is_file(TEMPLATE_XLS)) {
        throw new RuntimeException('template.xls が見つかりません。');
    }

    $paydayFmt = normalizePayday($payday);
    $codes = unionEmployeeCodes($csvKintai, $csvKyuka);

    $spreadsheet = IOFactory::load(TEMPLATE_XLS);
    $sheet = $spreadsheet->getActiveSheet();

    $row = 10; // 書き込み開始行

    foreach ($codes as $emp) {
        $kintai_row = $csvKintai[$emp] ?? [];
        $kyuka_row = $csvKyuka[$emp]  ?? [];
        $emp = str_pad((string)$emp, 7, '0', STR_PAD_LEFT);

        $vals = [];

        // 管理職判定
        $position_code = $kintai_row['職位コード'];
        $isManagementPosition = in_array($position_code, $managementPositionCodes, true);

        // フレックス勤務判定
        $contractName = $kintai_row['雇用契約名称'];
        $isFlexWork = ($contractName === '社員FL');

        // 月給者・時給者・育児勤務者判定
        $employee = $employeesWithApplications[$emp];
        $patternName = $employee['pattern_name'] ?? '';
        $isMonthly = str_contains($patternName, '月給者');
        $isHourly  = str_contains($patternName, '時給者');
        $childCareWorker  = str_contains($patternName, '育勤');

        // 変形労働加算対象者判定
        $isVariableWorker = ((!$isManagementPosition && $isMonthly) || $isHourly || $childCareWorker);

        // 1. お客様番号
        $vals[] = 'F380';

        // 2. 給与会社番号
        $vals[] = '001';

        // 3. 区分
        $vals[] = 'PAY010';

        // 4. 支給年月日
        $vals[] = $paydayFmt;

        // 5. 処理種別
        $vals[] = 'P';

        // 6. 処理種別分類
        $vals[] = '';

        // 7. 社員番号
        $vals[] = $emp;

        // 8. 入・出勤日数
        $vals[] = pickVal($kintai_row, ['出勤日数'], '0');

        // 9. 入・有休
        $vals[] = pickVal($kyuka_row, ['有給休暇(全休)'], '0.0');

        // 10. 勤務時間
        if ($isFlexWork) {
            // -- フレックス勤務
            // ---------  ▼ 未実装 -----------
            // 休暇取得日数の合計値を取得
            // 基準時間の計算
            // 勤務時間を算出
            // 残業時間を算出
            $vals[]  = minutesOrHhmmToHourMinuteStr('99:99');
            // ---------  ▲ 未実装 -----------
        } else {
            // -- 非フレックス勤務
            // 勤怠集計(勤務時間) - 勤怠集計(法定外残業時間(週40時間超除く)) - 勤怠集計(法定内残業時間(週40時間超除く))を計算
            $work_time = (string)(
                (int)$kintai_row['勤務時間']
                - (int)$kintai_row['法定外残業時間(週40時間超除く)']
                - (int)$kintai_row['法定内残業時間(週40時間超除く)']
            );
            $vals[] = minutesOrHhmmToHourMinuteStr($work_time);
        }

        // 11. 入・普通残業時間
        if ($isFlexWork) {
            // -- フレックス勤務
            // 残業時間を格納する
            $vals[]  = minutesOrHhmmToHourMinuteStr('99:99');
        } else if ($isManagementPosition) {
            // -- 管理職
            // ゼロを格納する
            $vals[] = '0.00';
        } else {
            // -- 非フレックス勤務・非管理職
            // ---------  ▼ 未実装 -----------
            // 残業時間割増処理
            // if ($isVariableWorker){}
            $vals[] = pickMinutesAsHourMinuteStr($kintai_row, ['法定外残業時間(週40時間超除く)'], '0.00');
            // ---------  ▲ 未実装 -----------
        }

        // 12. 入・深夜時間
        $vals[] = pickMinutesAsHourMinuteStr($kintai_row, ['深夜時間'], '0.00');

        // 13. 入・深夜残業時間
        if ($isFlexWork) {
            // -- フレックス勤務
            // ゼロを格納する            
            $vals[] = '0.00';
        } else if ($isManagementPosition){
            // -- 管理職
            // ゼロを格納する            
            $vals[] = '0.00';
        } else {
            // -- 非フレックス勤務・非管理職
            $vals[] = pickMinutesAsHourMinuteStr($kintai_row, ['深夜時間外時間'], '0.00');
        }

        // 14. 入・法定内残業時間
        if ($isFlexWork) {
            // -- フレックス勤務
            // ゼロを格納する
            $vals[] = '0.00';
        } else if ($isManagementPosition) {
            // -- 管理職
            // 勤怠集計(法定外残業時間(週40時間超除く)) + 勤怠集計(法定内残業時間(週40時間超除く))を格納
            $vals[] = minutesOrHhmmToHourMinuteStr(
                (string)(
                    (int)$kintai_row['法定外残業時間(週40時間超除く)'] +
                    (int)$kintai_row['法定内残業時間(週40時間超除く)'])
            );
        } else {
            // -- 非フレックス勤務・非管理職
            $vals[] = pickMinutesAsHourMinuteStr($kintai_row, ['法定内残業時間(週40時間超除く)'], '0.00');
        }

        // 15. 休日出勤時間
        $vals[] = '0';

        // 16. 入・減給時間
        if ($isFlexWork) {
            // -- フレックス勤務
            // ---------  ▼ 未実装 -----------
            // 対象月の基準時間不足分を計算する
            $vals[] = '99.99';
            // ---------  ▲ 未実装 -----------
        } else if ($isManagementPosition) {
            // -- 管理職
            // ゼロを格納する
            $vals[] = '0.00';
        } else {
            // -- 非フレックス勤務・非管理職
            $vals[] = pickMinutesAsHourMinuteStr($kintai_row, ['減額対象時間'], '-');
        }

        // 17. 入・病欠（１００）
        $vals[] = '0';

        // 18. 入・病欠（１５０）
        $vals[] = pickVal($kyuka_row, ['病欠150(全休)【38】'], '-');

        // 19. 入・認欠（７５）
        $vals[] = '0';

        // 20. 入・振休
        $vals[] = pickVal($kintai_row, ['振替休日日数'], '-');

        // 21. 入・交休
        $vals[] = pickVal($kintai_row, ['交替休日日数'], '-');

        // 22. 入・休日
        $vals[] = pickVal($kyuka_row, ['休日(全休)【28】'], '-');

        // 23. 入・前有
        $vals[] = pickVal($kyuka_row, ['有給休暇(午前)'], '-');

        // 24. 入・後有
        $vals[] = pickVal($kyuka_row, ['有給休暇(午後)'], '-');

        // 25. 入・前期
        $vals[] = pickVal($kyuka_row, ['前期特別休暇(全休)【18】'], '-');

        // 26. 入・後期
        $vals[] = pickVal($kyuka_row, ['後期特別休暇(全休)【19】'], '-');

        // 27. 入・公休
        $vals[] = pickVal($kyuka_row, ['公休(全休)【44】'], '-');

        // 28. 入・前振
        $vals[] = pickVal($kyuka_row, ['前振(全休)【13】'], '-');

        // 29. 入・忌引
        $vals[] = pickVal($kyuka_row, ['忌引休暇(全休)【22】'], '-');

        // 30. 入・結婚
        $vals[] = pickVal($kyuka_row, ['結婚休暇(全休)【20】'], '-');

        // 31. 入・産有
        $vals[] = pickVal($kyuka_row, ['産休（有給）(全休)【23】'], '-');

        // 32. 入・産無
        $vals[] = pickVal($kyuka_row, ['産休（無給）(全休)【25】'], '-');

        // 33. 入・育職
        $vals[] = pickVal($kyuka_row, ['育児休職(全休)【40】'], '-');

        // 34. 入・介職
        $vals[] = pickVal($kyuka_row, ['介護休職(全休)【41】'], '-');

        // 35. 入・無欠（無給）
        $vals[] = pickVal($kyuka_row, ['無欠無給(全休)【29】'], '-');

        // 36. 入・看休（無給）
        $vals[] = pickVal($kyuka_row, ['介護・看護休暇（無給）(全休)【27】'], '-');

        // 37. 入・生休（無給）
        $vals[] = pickVal($kyuka_row, ['生理休暇（無給）(全休)【26】'], '-');

        // 38. 入・労災
        $vals[] = pickVal($kyuka_row, ['労災欠勤(全休)【45】'], '-');

        // 39. 入・災害
        $vals[] = pickVal($kyuka_row, ['災害休暇(全休)【46】'], '-');

        // 40. 入・育勤
        $vals[] = '0';

        // 41. 入・介勤
        $vals[] = '0';

        // 先頭から順に書き込み
        for ($i = 1; $i <= count($vals); $i++) {
            $sheet->setCellValueExplicit(xlCol($i + 1) . $row, $vals[$i - 1], DataType::TYPE_STRING);
        }
        $row++;
    }

    // 保存
    $out = tempnam(sys_get_temp_dir(), 'xls_') . '.xls';
    $writer = IOFactory::createWriter($spreadsheet, 'Xls');
    $writer->save($out);
    return $out;
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
