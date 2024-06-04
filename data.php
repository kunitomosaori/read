<?php
// エラー表示
ini_set("display_errors", 1);
error_reporting(E_ALL);

// 1. DB接続します
try {
    // Password: MAMP='root', XAMPP=''
    $pdo = new PDO('mysql:dbname=gs_db1;charset=utf8;host=localhost', 'root', '');
} catch (PDOException $e) {
    exit('DB_CONECTERROR!:' . $e->getMessage());
}

// 2. データ登録SQL作成
$sql = "SELECT * FROM gs_bm_table";
$stmt = $pdo->prepare($sql);
$status = $stmt->execute();

// 3. データ表示
if ($status == false) {
    // execute（SQL実行時にエラーがある場合）
    $error = $stmt->errorInfo();
    exit("SQL_ERROR!:" . $error[2]);
}

// 全データ取得
$values = $stmt->fetchAll(PDO::FETCH_ASSOC); // PDO::FETCH_ASSOC[カラム名のみで取得できるモード]

// グラフ1用データを集計
$sql1 = "SELECT book_name, COUNT(comment) AS comment_count FROM gs_bm_table GROUP BY book_name";
$stmt1 = $pdo->prepare($sql1);
$status1 = $stmt1->execute();

if ($status1 == false) {
    $error = $stmt1->errorInfo();
    exit("SQL_ERROR!:" . $error[2]);
}

$chartData = $stmt1->fetchAll(PDO::FETCH_ASSOC);
$json = json_encode($chartData, JSON_UNESCAPED_UNICODE);

// 年代別データを集計
$sql2 = "SELECT CASE
                WHEN age BETWEEN 10 AND 19 THEN '10代'
                WHEN age BETWEEN 20 AND 29 THEN '20代'
                WHEN age BETWEEN 30 AND 39 THEN '30代'
                WHEN age BETWEEN 40 AND 49 THEN '40代'
                WHEN age BETWEEN 50 AND 59 THEN '50代'
                WHEN age >= 60 THEN '60代以上'
                ELSE '不明'
            END AS age_group,
            COUNT(*) AS count
        FROM gs_bm_table
        GROUP BY age_group";
$stmt2 = $pdo->prepare($sql2);
$status2 = $stmt2->execute();

if ($status2 == false) {
    $error = $stmt2->errorInfo();
    exit("SQL_ERROR!:" . $error[2]);
}

$ageData = $stmt2->fetchAll(PDO::FETCH_ASSOC);
$ageJson = json_encode($ageData, JSON_UNESCAPED_UNICODE);

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>フリーアンケート表示</title>
    <link rel="stylesheet" href="css/range.css">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <style>
        div {
            padding: 10px;
            font-size: 16px;
        }

        td {
            border: 1px solid red;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <!-- Chart.jsの読み込み -->
</head>

<body id="main">
    <!-- Head[Start] -->
    <header>
        <nav class="navbar navbar-default">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" href="index.php">データ登録</a>
                </div>
            </div>
        </nav>
    </header>
    <!-- Head[End] -->

    <!-- Main[Start] -->
    <div>
        <div class="container jumbotron">
            <table>
                <tr>
                    <td>id</td>
                    <td>名前</td>
                    <td>性別</td>
                    <td>年齢</td>
                    <td>書籍名</td>
                    <td>URL</td>
                    <td>感想</td>
                    <td>登録時間</td>
                </tr>
                <?php foreach ($values as $value) { ?>
                    <tr>
                        <td><?= htmlspecialchars($value["id"], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($value["name"], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php
                            if ($value["gender"] === 'male') {
                                echo '男性';
                            } elseif ($value["gender"] === 'female') {
                                echo '女性';
                            } else {
                                echo '他';
                            }
                            ?>
                        </td>
                        <td><?= htmlspecialchars($value["age"], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($value["book_name"], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><a href="<?= htmlspecialchars($value["url"], ENT_QUOTES, 'UTF-8') ?>" target="_blank">
                                <?= htmlspecialchars($value["book_name"], ENT_QUOTES, 'UTF-8') ?></a>
                        </td>
                        <td><?= htmlspecialchars($value["comment"], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($value["indate"], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php } ?>
            </table>
        </div>
    </div>
    <!-- Main[End] -->

    <!-- グラフを表示するためのキャンバス -->
    <div class="container">
        <canvas id="myChart"></canvas>
        <canvas id="ageChart"></canvas> <!-- 年代別円グラフを追加 -->
    </div>

    <script>
        // PHPからJSONデータを取得
        const jsonData = '<?= $json ?>';
        const data = JSON.parse(jsonData);
        console.log(data);

        // データを整形（書籍名ごとに登録されたコメント数を集計）
        const labels = data.map(item => item.book_name);
        const commentCounts = data.map(item => item.comment_count);
        console.log(labels);
        console.log(commentCounts);

        // Chart.jsで棒グラフを描画
        const ctx = document.getElementById('myChart').getContext('2d');
        const myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '本ごとのコメント数',
                    data: commentCounts,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1, // メモリの間隔を整数に設定
                            callback: function(value) {
                                if (Number.isInteger(value)) {
                                    return value;
                                }
                            }
                        }
                    }
                }
            }
        });

        // 年代別データを整形
        const ageJsonData = '<?= $ageJson ?>';
        const ageData = JSON.parse(ageJsonData);
        console.log(ageData);

        const ageLabels = ageData.map(item => item.age_group);
        const ageCounts = ageData.map(item => item.count);
        console.log(ageLabels);
        console.log(ageCounts);

        // Chart.jsで円グラフを描画
        const ageCtx = document.getElementById('ageChart').getContext('2d');
        const ageChart = new Chart(ageCtx, {
            type: 'pie',
            data: {
                labels: ageLabels,
                datasets: [{
                    label: '年代別読書割合',
                    data: ageCounts,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 206, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)',
                        'rgba(255, 159, 64, 0.2)',
                        'rgba(201, 203, 207, 0.2)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(201, 203, 207, 1)'
                    ],
                    borderWidth: 1
                }]
            }
        });
    </script>
</body>

</html>