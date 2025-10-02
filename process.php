<?php
// 共通関数 / DB接続
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/config/dbconn_config_saiattendance.php'; // $pdo_sai
require_once __DIR__ . '/config/dbconn_config_stcmospv4.php';     // $pdo_mosp

// 設定
const SQL_HUMAN_STATUS      = 'select_human_status.sql';
const SQL_APPLICATION       = 'select_application.sql';
const SQL_CONTRACT_WORKTIME = 'get_contract_worktime.sql';
const SQL_FLEX_STANDARDS    = 'get_flex_standards.sql';
const SQL_POSITION          = 'get_position_master.sql';
const SQL_HALF_HOLIDAY      = 'select_both_half_holiday_count.sql';
const SQL_INSERT_PROSRV_IMPORT = 'insert_prosrv_import.sql';
const SQL_INSERT_VARIED_OVERTIME_EMPLOYEE = 'insert_varied_overtime_employee.sql';

// 初期化
ensureDirs();
$p1 = $p2 = $xlsPath = null;

try {
    // アップロード受領
    $p1 = saveUpload('csv1');  // 勤怠集計CSV（001-）
    $p2 = saveUpload('csv2');  // 休暇取得CSV（002-）
    if (!$p1 || !$p2) {
        throw new RuntimeException('CSV①/CSV②のアップロードに失敗しました。');
    }

    // フォーム値取得
    $targetMonth = $_POST['target_month'] ?? ''; // 例: 202508
    $payday      = $_POST['payday'] ?? '';      // 例: 2025-08-25（type=date）

    // 入力検証
    if (!preg_match('/^\d{6}$/', $targetMonth)) {
        throw new InvalidArgumentException('対象年月の形式が不正です（YYYYMM）。');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payday)) {
        throw new InvalidArgumentException('給与支給日の形式が不正です（YYYY-MM-DD）。');
    }

    // 年月の分解
    $year  = (int)substr($targetMonth, 0, 4);
    $month = (int)substr($targetMonth, 4, 2);

    // 月初日付をYYYY/MM/DD形式で取得
    $firstDayForRef = monthFirstDayYmdSlash($targetMonth);

    // 月末日付をYYYY/MM/DD形式で取得
    $lastDayForRef = monthLastDayYmdSlash($targetMonth);

    // CSV読込
    $csvKintai = loadCsv($p1);
    $csvKyuka  = loadCsv($p2);

    // 勤怠集計及び休暇取得のCSVにデータが存在するか確認
    if (empty($csvKintai) || empty($csvKyuka)) {
        throw new Exception('勤怠集計CSVあるいは休暇取得CSVにデータが存在しません。');
    }


    // 人事管理マスタを取得
    $empResult = fetchAllRows($pdo_mosp, SQL_HUMAN_STATUS, [
        ':target_date' => $lastDayForRef,
    ]);

    // 設定適用マスタを取得
    $applicationResult = fetchAllRows($pdo_mosp, SQL_APPLICATION, [
        ':target_date' => $lastDayForRef,
    ]);

    // 人事管理マスタと設定適用マスタを統合
    $employeesWithApplications = getApplicationSettingsToEmployees($empResult, $applicationResult);
    $employeesWithApplications = array_column($employeesWithApplications, null, 'employee_code');

    // 契約所定時間（分）を取得
    $contractRow = fetchOne($pdo_sai, SQL_CONTRACT_WORKTIME, [
        ':target_year'  => $year,
        ':target_month' => $month,
    ]);
    $contractWorkMinutes = $contractRow['work_minutes'] ?? 0;
    if ($contractWorkMinutes === 0) {
        throw new RuntimeException('契約所定労働時間が未登録のため処理を続行できません。');
    }

    // フレックス基準時間（分）を取得
    $flexRow = fetchOne($pdo_sai, SQL_FLEX_STANDARDS, [
        ':target_year'  => $year,
        ':target_month' => $month,
    ]);
    $flexStandardMinutes = $flexRow['flex_minutes'] ?? 0;
    if ($flexStandardMinutes === 0) {
        throw new RuntimeException('フレックス基準時間が未登録のため処理を続行できません。');
    }
    // 管理職の職位コードを取得
    $positionRow = fetchAllRows($pdo_sai, SQL_POSITION,[]);
    $managementPositionCodes = array_column($positionRow, 'position_code');

    // 指定期間内で「同日に前有・後有を取得している」データを取得
    $halfHolidayCountRow = fetchAllRows($pdo_mosp, SQL_HALF_HOLIDAY, [
        ':start_date' => $firstDayForRef,
        ':end_date'   => $lastDayForRef,
    ]);
    // 取得結果を employee_code をキーにした連想配列へ変換
    $halfHolidayByEmp = [];
    if (!empty($halfHolidayCountRow)) {
        // 「employee_code が NULL かつ both_half_holiday_count が 0」だけのケースは除外
        if (!(count($halfHolidayCountRow) === 1 
            && $halfHolidayCountRow[0]['employee_code'] === null 
            && (int)$halfHolidayCountRow[0]['both_half_holiday_count'] === 0)) {
            
            foreach ($halfHolidayCountRow as $row) {
                $code = str_pad((string)$row['employee_code'], 7, '0', STR_PAD_LEFT); 
                $halfHolidayByEmp[$code] = [
                    'both_half_holiday_count' => (int)$row['both_half_holiday_count'],
                ];
            }
        }
    }

    // Excel生成
    $result = buildXlsToTempByEmployee($csvKintai, $csvKyuka, $payday, $employeesWithApplications, 
                                        $contractWorkMinutes, $flexStandardMinutes, $managementPositionCodes, $halfHolidayByEmp);
    $xlsPath = $result['out'];
    $excelValues = $result['excelValues'];
    $variedOvertimeValues = $result['variedOvertimeValues'];

    // SaiAttendanceDBの prosrv_import テーブルにデータを挿入する
    // 勤怠集計対象日付
    $target_date = monthFirstDayYmdSlash($targetMonth);

    // 連想配列にしたExcel出力データ配列 excelValues をパラメータとして利用する
    foreach ($excelValues as $row) {
        // prosrv_import テーブルに追加するパラメータ配列を作成
        $prosrv_import_params = array_combine(
            keys:[
                ':customer_no', ':company_no', ':category', ':payment_date', ':process_type', ':process_class', ':employee_no', ':work_days', ':paid_leave',
                ':work_hours', ':overtime_normal', ':midnight_hours', ':midnight_overtime', ':legal_overtime', ':holiday_work_hours', ':deduction_hours', ':sick_100',
                ':sick_150', ':recogn_sick_75', ':substitute_leave', ':exchange_leave', ':holiday', ':prev_paid_leave', ':next_paid_leave', ':prev_term_leave',
                ':next_term_leave', ':public_holiday', ':prev_substitute', ':condolence_leave', ':marriage_leave', ':maternity_paid', ':maternity_unpaid',
                ':childcare_leave', ':nursing_leave', ':unpaid_absence', ':unpaid_nursing', ':unpaid_birth', ':industrial_accident', ':disaster_leave', ':childcare_work',
                ':nursing_work', ':target_date'],
            values:[
                $row['customer_number'],$row['company_code'],$row['category'],$row['payday'],$row['process_type'],$row['process_subtype'],$row['employee_number'],$row['work_days'],$row['paid_holiday_full'],
                $row['work_time'],$row['normal_overtime'],$row['late_night_time'],$row['late_night_overtime'],$row['legal_overtime'],$row['holiday_work'],$row['paycut_time'],$row['sick_leave_100'],
                $row['sick_leave_150'],$row['ninketsu_75'],$row['substitute_holiday'],$row['alternating_holiday'],$row['public_holiday'],$row['paid_holiday_half_am'],$row['paid_holiday_half_pm'],$row['special_holiday_am'],
                $row['special_holiday_pm'],$row['official_holiday'],$row['substitute_holiday_am'],$row['bereavement_leave'],$row['marriage_leave'],$row['maternity_leave_paid'],$row['maternity_leave_unpaid'],
                $row['childcare_leave'],$row['nursing_care_leave'],$row['unpaid_leave'],$row['nursing_care_unpaid'],$row['menstruation_leave'],$row['workers_compensation'],$row['disaster_leave'],$row['childcare_work'],
                $row['nursing_care_work'], $target_date
            ]
        );

        // prosrv_import テーブルに追加する（employee_noとtarget_dateの組み合わせが既に存在する場合は更新）
        insertRows($pdo_sai, SQL_INSERT_PROSRV_IMPORT, $prosrv_import_params);
    }

    // 連想配列にした変形労働対象者データ配列にデータがある場合、 varied_overtime_employee テーブルに追加する
    if(!empty($variedOvertimeValues)){
        
        // varied_overtime_employee テーブルに追加するパラメータ配列を作成
        foreach($result['variedOvertimeValues'] as $vrow){
            $varied_overtime_employee_params = array_combine(
                keys:[
                    ':employee_no',':target_date',':work_hours',':normal_overtime_raw',
                    ':work_minutes',':normal_overtime_adj', ':contract_workminutes_overtime'],
                values:[
                    $vrow['employee_number'], $target_date, $vrow['work_time'], $vrow['overtime_nomal_raw'],
                    $vrow['contractWorkMinutes'], $vrow['overtime_nomal_adjusted'], $vrow['contractWorkMinutesOvertime']
                    ]
            );
        // varied_overtime_employee テーブルに追加する（employee_noとtarget_dateの組み合わせが既に存在する場合は更新）
        insertRows($pdo_sai,SQL_INSERT_VARIED_OVERTIME_EMPLOYEE, $varied_overtime_employee_params);
        }
    }

    // ダウンロード名
    $downloadName = 'ProsrvImport_' . date('Ymd_His') . '.xls';

    // 出力バッファ掃除
    if (ob_get_level()) {
        ob_end_clean();
    }

    // ヘッダ送出
    header('Content-Type: application/vnd.ms-excel');
    header(
        'Content-Disposition: attachment; filename="' . rawurlencode($downloadName) .
            '"; filename*=UTF-8\'\'' . rawurlencode($downloadName)
    );
    header('Content-Length: ' . filesize($xlsPath));

    // 本体送出
    readfile($xlsPath);

    // 正常終了
    exit;
} catch (Throwable $e) {
    // エラー応答
    http_response_code(500);
    echo 'エラー: ' . $e->getMessage();
} finally {
    // 後始末（存在チェックしてから削除）
    if (is_string($xlsPath) && is_file($xlsPath)) @unlink($xlsPath);
    if (is_string($p1)      && is_file($p1))      @unlink($p1);
    if (is_string($p2)      && is_file($p2))      @unlink($p2);
}

// YYYMM の月初を Y/m/d で返す
function monthFirstDayYmdSlash(string $ym): string
{
    if (!preg_match('/^\d{6}$/', $ym)) {
        throw new InvalidArgumentException('対象年月の形式が不正です（YYYYMM）。');
    }
    $y = (int)substr($ym, 0, 4);
    $m = (int)substr($ym, 4, 2);
    $dt = new DateTime(sprintf('%04d-%02d-01', $y, $m));
    return $dt->format('Y/m/d');
}

// YYYYMM の月末を Y/m/d で返す
function monthLastDayYmdSlash(string $ym): string
{
    if (!preg_match('/^\d{6}$/', $ym)) {
        throw new InvalidArgumentException('対象年月の形式が不正です（YYYYMM）。');
    }
    $y = (int)substr($ym, 0, 4);
    $m = (int)substr($ym, 4, 2);
    $dt = new DateTime(sprintf('%04d-%02d-01', $y, $m));
    $dt->modify('last day of this month');
    return $dt->format('Y/m/d');
}

// 指定されたSQLから1行取得する
function fetchOne(PDO $pdo, string $sqlFile, array $params): ?array
{
    $sql  = loadSql($sqlFile);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

// 指定されたSQLから複数行を取得する
function fetchAllRows(PDO $pdo, string $sqlFile, array $params): array
{
    $sql  = loadSql($sqlFile);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 指定されたSQLでテーブルにデータを挿入する
function insertRows(PDO $pdo, string $sqlFile, array $params)
{
    $sql  = loadSql($sqlFile);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}