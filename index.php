<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/config/dbconn_config_saiattendance.php';

// 今日の日付
$today = new DateTime();
if ((int)$today->format('d') <= 10) {
    $today->modify('-1 month');
}

// デフォルト対象年月
$default_ym = $today->format('Ym');
$default_year = (int)$today->format('Y');
$default_month = (int)$today->format('n');

// DBから支払日取得
$payment_date = '';
$sql = loadSql('get_payment_date.sql');
$stmt = $pdo_sai->prepare($sql);
$stmt->execute([
    ':year' => $default_year,
    ':month' => $default_month
]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    $payment_date = $row['payment_date'];
}

?>
<!doctype html>
<html lang="ja">

<head>
    <meta charset="utf-8">
    <title>Prosrv取込用Excel作成ツール</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/x-icon" href="images/favicon.png">
</head>

<body>
    <div class="wrap">
        <div class="card">
            <h1 class="title">Prosrv取込用Excel作成ツール</h1>
            <p class="subtitle">MosPのCSVファイルをアップロードし、Prosrv取込用Excelファイルを生成します。</p>

            <form action="process.php" method="post" enctype="multipart/form-data">

                <div class="field">
                    <label>対象年月</label>
                    <select name="target_month" id="target_month" required>
                        <?php
                        for ($i = 0; $i < 12; $i++) {
                            $optionDate = clone $today;
                            $optionDate->modify("-$i month");
                            if (
                                (int)$optionDate->format('Y') < 2025 ||
                                ((int)$optionDate->format('Y') == 2025 && (int)$optionDate->format('n') < 2)
                            ) {
                                continue;
                            }
                            $ym = $optionDate->format('Ym');
                            $selected = ($ym === $default_ym) ? 'selected' : '';
                            echo "<option value=\"$ym\" $selected>$ym</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="field">
                    <label>給与支給日</label>
                    <input type="date" id="payday" name="payday" value="<?= $payment_date ?>" required>
                </div>

                <div class="field">
                    <label>【MosP】勤怠集計CSVファイル　※ファイル名：「001_YYYYMMDD-YYYYMMDD.csv」</label>
                    <input type="file" name="csv1" accept=".csv" required>
                </div>

                <div class="field">
                    <label>【MosP】休暇取得CSVファイル　※ファイル名：「002_YYYYMMDD-YYYYMMDD.csv」</label>
                    <input type="file" name="csv2" accept=".csv" required>
                </div>

                <div class="actions">
                    <button type="submit" class="btn">出力</button>
                </div>

            </form>
        </div>
    </div>
    <script src="js/form-handler.js"></script>
</body>

</html>