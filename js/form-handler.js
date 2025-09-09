// 対象年月変更時に給与支給日を取得して更新
document.getElementById('target_month').addEventListener('change', function () {
    const ym = this.value;
    fetch('get_payment_date.php?ym=' + ym)
        .then(response => response.json())
        .then(data => {
            if (data.payment_date) {
                document.getElementById('payday').value = data.payment_date;
            } else {
                document.getElementById('payday').value = '';
            }
        })
        .catch(err => console.error(err));
});

// 要素の参照を取得
const csv1Input = document.querySelector('input[name="csv1"]'); // 勤怠集計CSV (001-)
const csv2Input = document.querySelector('input[name="csv2"]'); // 休暇取得CSV (002-)
const form = document.querySelector('form[action="process.php"]'); // フォーム
const targetSelect = document.getElementById('target_month');     // 対象年月セレクト

// ファイル名の形式チェック（プレフィックス・拡張子）
function validateFile(input, requiredPrefix) {
    if (!input.files || !input.files[0]) {
        input.setCustomValidity('');
        return true;
    }

    const name = input.files[0].name;
    const lower = name.toLowerCase();

    const hasPrefix = lower.startsWith(requiredPrefix.toLowerCase()); // 001- または 002-
    const isCsv = /\.csv$/i.test(name); // 拡張子が .csv かどうか

    if (!hasPrefix || !isCsv) {
        const parts = [];
        if (!hasPrefix) parts.push(`"${requiredPrefix}"で始まる`);
        if (!isCsv)    parts.push(`拡張子が .csv`);
        input.setCustomValidity(`ファイル名が不正です。${parts.join(' かつ ')} 必要があります。選び直してください。`);
        input.reportValidity();
        input.value = '';
        return false;
    }

    // 厳密形式チェック: 001-YYYYMMDD-YYYYMMDD.csv
    const strict = new RegExp(`^${requiredPrefix}(\\d{8})-(\\d{8})\\.csv$`, 'i');
    if (!strict.test(name)) {
        input.setCustomValidity(`ファイル名は ${requiredPrefix}YYYYMMDD-YYYYMMDD.csv の形式で指定してください。`);
        input.reportValidity();
        input.value = '';
        return false;
    }

    input.setCustomValidity('');
    return true;
}

// 対象年月から月初と月末の日付を算出（YYYYMMDD形式で返す）
function getMonthRange(ym) {
    if (!/^\d{6}$/.test(ym)) return null;
    const y = parseInt(ym.slice(0, 4), 10);
    const m = parseInt(ym.slice(4, 6), 10);

    const first = new Date(y, m - 1, 1);
    const last  = new Date(y, m, 0);

    const fmt = (d) => {
        const yy = d.getFullYear().toString().padStart(4, '0');
        const mm = (d.getMonth() + 1).toString().padStart(2, '0');
        const dd = d.getDate().toString().padStart(2, '0');
        return `${yy}${mm}${dd}`;
    };
    return { start: fmt(first), end: fmt(last) };
}

// ファイル名から期間部分 (YYYYMMDD-YYYYMMDD) を抽出
function extractRangeFromFilename(fileName, requiredPrefix) {
    const re = new RegExp(`^${requiredPrefix}(\\d{8})-(\\d{8})\\.csv$`, 'i');
    const m = fileName.match(re);
    if (!m) return null;
    return { start: m[1], end: m[2] };
}

// ファイル名の期間と対象年月の月初～月末が一致しているか確認
function validatePeriodMatchesTarget(input, requiredPrefix, ym) {
    if (!input.files || !input.files[0]) return true;

    const fileName = input.files[0].name;
    const range = extractRangeFromFilename(fileName, requiredPrefix);
    if (!range) {
        input.setCustomValidity(`ファイル名は ${requiredPrefix}YYYYMMDD-YYYYMMDD.csv の形式で指定してください。`);
        input.reportValidity();
        input.value = '';
        return false;
    }

    const monthRange = getMonthRange(ym);
    if (!monthRange) {
        alert('対象年月の値が不正です。');
        return false;
    }

    if (range.start !== monthRange.start || range.end !== monthRange.end) {
        input.setCustomValidity(`ファイル名の期間が対象年月と一致しません。`);
        input.reportValidity();
        input.value = '';
        return false;
    }

    input.setCustomValidity('');
    return true;
}

// ファイル選択時に即チェックを実行
csv1Input.addEventListener('change', () => validateFile(csv1Input, '001-'));
csv2Input.addEventListener('change', () => validateFile(csv2Input, '002-'));

// フォーム送信時に最終チェックを実行
form.addEventListener('submit', (e) => {
    const ok1 = validateFile(csv1Input, '001-');
    const ok2 = validateFile(csv2Input, '002-');
    if (!ok1 || !ok2) {
        e.preventDefault();
        return;
    }

    const ym = targetSelect.value;
    const p1 = validatePeriodMatchesTarget(csv1Input, '001-', ym);
    const p2 = validatePeriodMatchesTarget(csv2Input, '002-', ym);
    if (!p1 || !p2) {
        e.preventDefault();
        return;
    }
});
