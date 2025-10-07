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
const SQL_INSERT_EMPLOYEE_ATTENDANCE_RATE = 'insert_employee_attendance_rate.sql';
const SQL_SELECT_SHIFT_SCHEDULE_WITH_WORKTYPE = 'select_shift_schedule_with_worktype.sql';
const SQL_SELECT_SHIFT_WORKTYPE_CHANGE_REQUEST = 'select_shift_worktype_change_request.sql';
const SQL_SELECT_SHIFT_HOLIDAY_REQUEST = 'select_shift_holiday_request.sql';
const SQL_SELECT_HOLIDAY = 'select_holiday.sql';

const HOLIDAY_MASTER_CODE_Dictionary = [
    '13' => 'substitute_holiday_am',    // 前振
    '18' => 'special_holiday_am',    // 前期特別休暇
    '19' => 'special_holiday_pm',    // 後期特別休暇
    '20' => 'marriage_leave',    // 結婚休暇
    '22' => 'bereavement_leave',    // 忌引休暇
    '23' => 'maternity_leave_paid',    // 産休（有給）
    '25' => 'maternity_leave_unpaid',    // 産休（無給）
    '26' => 'menstruation_leave',    // 生理休暇（無給）
    '27' => 'nursing_care_unpaid',    // 介護・看護休暇（無給）
    '28' => 'public_holiday',    // 休日
    '29' => 'unpaid_leave',    // 無欠無給
    '38' => 'sick_leave_150',    // 病欠150
    '39' => 'recuperation_80',    // 療養80
    '40' => 'childcare_leave',    // 育児休暇
    '41' => 'nursing_care_leave',    // 介護休暇
    '43' => 'attendance_stop',    // 出勤停止
    '44' => 'official_holiday',    // 公休
    '45' => 'workers_compensation',    // 労災欠勤
    '46' => 'disaster_leave',    // 災害休暇
];


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

    // Excel生成、DB登録
    $result = buildXlsToTempByEmployee($csvKintai, $csvKyuka, $payday, $employeesWithApplications, 
                                        $contractWorkMinutes, $flexStandardMinutes, $managementPositionCodes, $halfHolidayByEmp);
    $xlsPath = $result['out'];      // 生成されたExcelファイルのパス
    $excelValues = $result['excelValues'];      // Excel出力データ
    $variedOvertimeValues = $result['variedOvertimeValues'];                                // 変形労働対象者データ
    $attendanceDataForAttendanceRateCal = $result['attendanceDataForAttendanceRateCal'];    // 出勤率データを作成するための勤怠データ

    // SaiAttendanceDBのテーブルにデータを挿入する 勤怠集計対象日付 を変数に格納する
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
    
    // シフト表を取得する
    $shift = getShiftTable($pdo_mosp, $firstDayForRef, $lastDayForRef, $employeesWithApplications);
    
    // シフト表から対象社員のシフトデータを抽出する    
    foreach($excelValues as $ev){
        $empCode = $ev['employee_number'];

        // 該当社員のシフトデータを取得する
        foreach($shift as $s){
            if($s['employee_code'] === $empCode){
                $reShift[] = $s;
            }
        }
    }
    
    // 月初時点で有効な最新の休暇種別マスタを取得する
    $holidayTypesMaster = fetchAllRows($pdo_mosp, SQL_SELECT_HOLIDAY, [':target_date'  => $firstDayForRef]);

    // 予定出勤日数・予定公休日数を格納した連想配列を取得する
    $CalcForShiftArray = getPlannedWorkingDaysAndHolidays($reShift);

    
    // 社員別出勤率データを格納した連想配列を取得する
    $employeeAttendanceRateArray = getEmployeeAttendanceRateArray(
        $attendanceDataForAttendanceRateCal, $CalcForShiftArray, $holidayTypesMaster);
    

    // 連想配列にした社員別出勤率データ配列にデータがある場合、 employee_attendance_rate テーブルに追加する
    if(!empty($employeeAttendanceRateArray)){
        // employee_attendance_rate テーブルに追加するパラメータ配列を作成
        foreach($employeeAttendanceRateArray as $arow){
            $employee_attendance_rate_params = array_combine(
                keys:[
                    ':employee_code', ':ym', ':planned_working_days', ':planned_holidays', ':actual_working_days',
                    ':actual_attendance_rate', ':paid_leave_calc_working_days', ':paid_leave_calc_attendance_rate'
                ],
                values:[
                    $arow['employee_number'], $targetMonth, $arow['planned_working_days'], $arow['planned_holidays'], $arow['actual_working_days'],
                    $arow['actual_attendance_rate'], $arow['paid_leave_calc_working_days'], $arow['paid_leave_calc_attendance_rate']
                ]
            );
            // employee_attendance_rate テーブルに追加する（employee_noとtarget_dateの組み合わせが既に存在する場合は更新）
            insertRows($pdo_sai, SQL_INSERT_EMPLOYEE_ATTENDANCE_RATE, $employee_attendance_rate_params);
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


/**
 * シフト表を取得する
 * 
 */
function getShiftTable(PDO $pdo, $firstDayForRef, $lastDayForRef ,$employeesWithApplications): array{
    // 範囲パラメータ作成
    $scheParams = [
        'target_date'     => $firstDayForRef,
        'termStart_date'  => $firstDayForRef,
        'termEnd_date'    => $lastDayForRef
    ];
    $holidayParams = [
        'termStart_date'  => $firstDayForRef,
        'termEnd_date'    => $lastDayForRef
    ];

    // データ取得（SQL実行）
    $scheResult      = fetchAllRows($pdo, SQL_SELECT_SHIFT_SCHEDULE_WITH_WORKTYPE, $scheParams);
    $workchngResult  = fetchAllRows($pdo, SQL_SELECT_SHIFT_WORKTYPE_CHANGE_REQUEST,['target_date' => $firstDayForRef]);
    $holidayResult   = fetchAllRows($pdo, SQL_SELECT_SHIFT_HOLIDAY_REQUEST, $holidayParams);

    // 各種データ結合処理
    $shift = getShift($employeesWithApplications, $scheResult, $workchngResult, $holidayResult);

    return $shift;
}

/**
 * 予定出勤日数・予定公休日数を取得する
 * 社員番号ごとに連想配列を作成する
 */
function getPlannedWorkingDaysAndHolidays($shift): array{
    
    // 戻り値となる連想配列を初期化
    $empWorkResult = [];
    
    // 社員番号ごとに連想配列を作成する
    // 社員番号を格納する変数を初期化
    $employee_code = 0; 
    
    
    foreach($shift as $shiftRow){
        $employee_code = $shiftRow['employee_code'];

        $prescribed_holiday = 0;    // 振替休日
        $legal_holiday = 0;      // 交替休日
        $work_day = 0;         // 出勤日
        
        foreach($shiftRow['schedule_code'] as $sche){
                // work_type_code ごとに判定する
                $work_type_code = $sche['work_type_code'];

                switch($work_type_code){
                    case 'prescribed_holiday': // 振替休日
                        $prescribed_holiday += 1;
                        break;

                    case 'legal_holiday': // 交替休日
                        $legal_holiday += 1;
                        break;
                    
                    case '': // 空欄の日
                        // $empWorkShiftResult[$employee_code]['planned_holidays'] += 1;
                        break;
                    default:    // それ以外の勤務形態コード(出勤日)
                        $work_day += 1;
                        break;
                }
        }
        // 予定出勤日数・予定公休日数を変数に格納する
                $empWorkResult[$employee_code] = [
                    'planned_working_days' => $work_day,     // 予定出勤日数
                    'planned_holidays' => $prescribed_holiday + $legal_holiday,  // 予定公休日数
                ];
    }    

    // 社員番号ごとの連想配列を返す
    return $empWorkResult;
}


/**
 * 社員別出勤率データを格納した連想配列を返す
 * 
 */
function getEmployeeAttendanceRateArray($attendanceDataForAttendanceRateCal, $CalcForShiftArray, $holidayTypesMaster): array{
    // 戻り値となる連想配列を初期化する
    $employeeAttendanceRateArray = [];

    // 休暇マスタを「出勤扱い」「欠勤扱い」「計算対象外」に分類する
    $paidLeaveTypes = [];   // 出勤扱い休暇
    $absentLeaveTypes = []; // 欠勤扱い休暇
    $nonCalcLeaveTypes = []; // 計算対象外休暇
    foreach($holidayTypesMaster as $htm){
        $code = $htm['holiday_code'];
        $calc_type = $htm['paid_holiday_calc'];
        switch($calc_type){
            case '1': // 出勤扱い
                $paidLeaveTypes[] = $code;
                break;
            case '2': // 欠勤扱い
                $absentLeaveTypes[] = $code;
                break;
            case '3': // 計算対象外
                $nonCalcLeaveTypes[] = $code;
                break;
            default:
                // それ以外は無視
                break;
        }
    }
        
        // 出勤率計算用の勤怠データから該当社員のデータを取得する
        foreach($attendanceDataForAttendanceRateCal as $adfar){            
            $employee_code = $adfar['employee_number'];   // 社員番号 

            // --- 実出勤日数 ---
            $actual_working_days = $adfar['work_days'];
            
            // --- 各休暇取得日数 --
            $paid_leave_calc_working_holidays = 0;  // 出勤扱い休暇 
            $absent_holiday_days = 0;   // 欠勤扱い休暇
            $non_calc_holiday_days = 0; // 計算対象外休暇

            // 出勤扱い休暇を取得日数に加算する
            $paid_leave_calc_working_holidays = calcHolidayDaysForAttendanceRate($paidLeaveTypes, $adfar);
            // 欠勤扱い休暇を取得日数に加算する
            $absent_holiday_days = calcHolidayDaysForAttendanceRate($absentLeaveTypes, $adfar);
            // 計算対象外休暇を取得日数に加算する
            $non_calc_holiday_days = calcHolidayDaysForAttendanceRate($nonCalcLeaveTypes, $adfar);

            // --- 有給計算用出勤日数(実出勤日数 + 年次有給休暇取得日数 + 出勤扱いになる休日数) ---
            $paid_leave_calc_working_days = $actual_working_days + $adfar['paid_holiday_total'] + $paid_leave_calc_working_holidays;

            // --- 実出勤率(実出勤日数 / 予定出勤日数) ---
            $actual_attendance_rate = 0.0;
            if(isset($CalcForShiftArray[$employee_code]) && $CalcForShiftArray[$employee_code]['planned_working_days'] > 0){
                $actual_attendance_rate = round($actual_working_days / $CalcForShiftArray[$employee_code]['planned_working_days'], 4)*100;
            }

            // --- 有給計算用出勤率(有給計算用出勤日数 / （予定出勤日数 - 計算対象外休暇日数）) ---
            $paid_leave_calc_attendance_rate = 0.0;
            if(isset($CalcForShiftArray[$employee_code]) && $CalcForShiftArray[$employee_code]['planned_working_days'] > 0){
                $paid_leave_calc_attendance_rate = round($paid_leave_calc_working_days / ($CalcForShiftArray[$employee_code]['planned_working_days'] - $non_calc_holiday_days), 4)*100;
            }

            // 社員別出勤率データを格納した連想配列に格納する
            $employeeAttendanceRateArray[] = [
                'employee_number' => $employee_code,
                'planned_working_days' => $CalcForShiftArray[$employee_code]['planned_working_days'] ?? 0,
                'planned_holidays' => $CalcForShiftArray[$employee_code]['planned_holidays'] ?? 0,
                'actual_working_days' => $actual_working_days,
                'actual_attendance_rate' => $actual_attendance_rate,
                'paid_leave_calc_working_days' => $paid_leave_calc_working_days,
                'paid_leave_calc_attendance_rate' => $paid_leave_calc_attendance_rate
            ];
        }
    
    return $employeeAttendanceRateArray;
}

/**
 * 休暇取得日数を加算する処理
 * 
 */ 
function calcHolidayDaysForAttendanceRate($holidayTypeMasterArray, $attendanceDataForAttendanceRateCal): int{
    $totalDays = 0;
    foreach($holidayTypeMasterArray as $hc){
        // attendanceDataForAttendanceRateCalの該当する休暇取得日数を加算する（HOLIDAY_MASTER_CODE_Dictionaryを利用）
        $code = HOLIDAY_MASTER_CODE_Dictionary[$hc] ?? null;
        if($code && isset($attendanceDataForAttendanceRateCal[$code])){
            $totalDays += $attendanceDataForAttendanceRateCal[$code];
        }
    }
    return $totalDays;
}