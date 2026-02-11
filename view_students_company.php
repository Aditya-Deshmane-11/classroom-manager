<?php
session_start();
require 'db.php'; 

// 1. --- SAFETY & SESSION ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'company') {
    header("Location: login.php?role=company");
    exit();
}
if (!isset($conn) || !$conn) {
    die("Database connection not available.");
}

$owner_name = $_SESSION['owner_name'] ?? 'Unknown';

// 2. --- FILTERS ---
// CHANGED: Replaced start/end date with single search_date
$search_date = $_GET['search_date'] ?? '';
$search_course = $_GET['search_course'] ?? '';

// Build WHERE clause
$where_clauses = ["owner_name = ?"];
$params = [$owner_name];
$types = "s";

// CHANGED: Single date filter logic
if ($search_date) {
    $where_clauses[] = "Interview_Date = ?";
    $params[] = $search_date;
    $types .= "s";
}

if ($search_course) {
    $where_clauses[] = "Course_Name = ?";
    $params[] = $search_course;
    $types .= "s";
}

$sql_where = " WHERE " . implode(" AND ", $where_clauses);

// 3. --- KPI CALCULATIONS ---
$kpi_sql = "SELECT 
    COUNT(DISTINCT Student_Name) as total_students, 
    COUNT(*) as total_interviews,
    AVG(Overall_Score) as avg_score,
    AVG(Interview_Duration_mins) as avg_duration
    FROM students" . $sql_where;

$q_kpi = $conn->prepare($kpi_sql);
$q_kpi->bind_param($types, ...$params);
$q_kpi->execute();
$kpi_data = $q_kpi->get_result()->fetch_assoc();
$q_kpi->close();

// 4. --- CHART: SCORE DISTRIBUTION ---
$dist_sql = "SELECT 
    CASE 
        WHEN Overall_Score >= 90 THEN 'Elite (90+)'
        WHEN Overall_Score >= 75 THEN 'Strong (75-89)'
        ELSE 'Average (<75)'
    END as score_tier,
    COUNT(*) as count
    FROM students" . $sql_where . " GROUP BY score_tier";
$q_dist = $conn->prepare($dist_sql);
$q_dist->bind_param($types, ...$params);
$q_dist->execute();
$res_dist = $q_dist->get_result();

$tier_labels = [];
$tier_counts = [];
while($r = $res_dist->fetch_assoc()) {
    $tier_labels[] = $r['score_tier'];
    $tier_counts[] = $r['count'];
}
$q_dist->close();

// 5. --- TABLE: TOP 10 PERFORMERS ---
// CHANGED: Added WhatsApp_Number to the select list
$top_performers = [];
$top_sql = "SELECT Student_Name, Course_Name, Overall_Score, Strong_Topics, Interview_Duration_mins, WhatsApp_Number 
            FROM students" . $sql_where . " ORDER BY Overall_Score DESC LIMIT 10";
$q_top = $conn->prepare($top_sql);
$q_top->bind_param($types, ...$params);
$q_top->execute();
$res_top = $q_top->get_result();
while ($r = $res_top->fetch_assoc()) {
    $top_performers[] = $r;
}
$q_top->close();

// 6. --- TABLE: ALL DATA ---
$all_rows = [];
$all_sql = "SELECT * FROM students" . $sql_where . " ORDER BY Interview_Date DESC";
$q_all = $conn->prepare($all_sql);
$q_all->bind_param($types, ...$params);
$q_all->execute();
$res_all = $q_all->get_result();
while ($r = $res_all->fetch_assoc()) {
    $all_rows[] = $r;
}
$q_all->close();

// 7. --- FILTER DROPDOWN DATA ---
$courses = [];
$q_c = $conn->prepare("SELECT DISTINCT Course_Name FROM students WHERE owner_name = ? ORDER BY Course_Name");
$q_c->bind_param("s", $owner_name);
$q_c->execute();
$res_c = $q_c->get_result();
while($r = $res_c->fetch_assoc()) $courses[] = $r['Course_Name'];
$q_c->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Hiring Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
    :root {
        --bg-color: #f5f5f0;
        --fg-color: #0a0a0a;
        --green-color: #98ff98;
        --grey-color: #ddd;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'Manrope', sans-serif;
        background: var(--bg-color);
        color: var(--fg-color);
        padding: 30px;
    }
    /* Header */
    .header-bar {
        display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;
    }
    h2 { font-family: 'Space Mono', monospace; font-size: 28px; margin: 0; }
    .btn {
        font-family: 'Manrope', sans-serif; font-weight: 700;
        padding: 10px 25px; background: var(--fg-color); color: #fff;
        border: 2px solid var(--fg-color); border-radius: 30px;
        text-decoration: none; cursor: pointer;
        box-shadow: 5px 5px 0px var(--green-color); transition: 0.2s;
    }
    .btn:hover { transform: translateY(-2px); box-shadow: 7px 7px 0px var(--green-color); }
    
    /* Filter Bar */
    .filter-bar {
        background: #fff; border: 2px solid var(--fg-color);
        padding: 20px; border-radius: 12px; box-shadow: 6px 6px 0px var(--fg-color);
        display: flex; gap: 15px; align-items: center; margin-bottom: 30px; flex-wrap: wrap;
    }
    select, input {
        padding: 10px; border: 2px solid var(--fg-color); border-radius: 8px;
        font-family: 'Manrope', sans-serif;
    }
    
    /* Grid Layout */
    .dashboard-grid {
        display: grid; grid-template-columns: repeat(4, 1fr); gap: 25px;
    }
    .col-1 { grid-column: span 1; }
    .col-2 { grid-column: span 2; }
    .col-3 { grid-column: span 3; }
    .col-4 { grid-column: span 4; }

    /* Cards */
    .card {
        background: #fff; border: 2px solid var(--fg-color);
        border-radius: 12px; padding: 25px;
        box-shadow: 6px 6px 0px var(--fg-color);
    }
    
    /* KPIs */
    .kpi-title { font-size: 14px; color: #555; font-weight: 700; text-transform: uppercase; }
    .kpi-value { font-family: 'Space Mono', monospace; font-size: 36px; font-weight: 700; margin-top: 5px; }
    
    /* Tables */
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th { text-align: left; padding: 12px; background: var(--fg-color); color: #fff; font-family: 'Space Mono', monospace; font-size: 14px; }
    td { padding: 12px; border-bottom: 1px solid #eee; font-size: 14px; }
    tr:hover td { background: #f9f9f9; }
    .badge-elite { background: #98ff98; padding: 4px 8px; border-radius: 4px; font-weight: 700; border: 1px solid #000; font-size: 12px; }
    
    @media (max-width: 1000px) { .col-2, .col-3 { grid-column: span 4; } }
    </style>
</head>
<body>

<div class="header-bar">
    <div>
        <h2>Candidate Scouting Dashboard</h2>
        <p>Reviewing candidates for: <strong><?= htmlspecialchars($owner_name) ?></strong></p>
    </div>
    <div>
        <button onclick="window.print()" class="btn"><i class="fa-solid fa-print"></i> Save Report</button>
        <a href="dashboard_company.php" class="btn">Back</a>
    </div>
</div>

<form method="GET" class="filter-bar">
    <label><strong>Filter Candidates:</strong></label>
    <select name="search_course">
        <option value="">All Courses</option>
        <?php foreach($courses as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>" <?= $search_course == $c ? 'selected' : '' ?>>
                <?= htmlspecialchars($c) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <input type="date" name="search_date" value="<?= htmlspecialchars($search_date) ?>">
    <input type="submit" value="Apply Filters" class="btn" style="padding: 8px 20px; font-size: 14px;">
</form>

<div class="dashboard-grid">
    <div class="card col-1">
        <div class="kpi-title">Candidates Evaluated</div>
        <div class="kpi-value"><?= $kpi_data['total_students'] ?></div>
    </div>
    <div class="card col-1">
        <div class="kpi-title">Total Interviews</div>
        <div class="kpi-value"><?= $kpi_data['total_interviews'] ?></div>
    </div>
    <div class="card col-1">
        <div class="kpi-title">Avg. Candidate Score</div>
        <div class="kpi-value"><?= round($kpi_data['avg_score'], 1) ?></div>
    </div>
    <div class="card col-1">
        <div class="kpi-title">Avg. Interview Time</div>
        <div class="kpi-value"><?= round($kpi_data['avg_duration'], 0) ?>m</div>
    </div>

    <div class="card col-3">
        <h3 style="margin:0; font-family:'Space Mono';"><i class="fa-solid fa-star" style="color:gold;"></i> Top 10 Candidates</h3>
        <table>
            <tr>
                <th>Candidate Name</th>
                <th>Course</th>
                <th>Score</th>
                <th>Strongest Skill</th>
                <th>Contact</th>
            </tr>
            <?php if (count($top_performers) > 0): ?>
                <?php foreach ($top_performers as $p): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($p['Student_Name']) ?></strong></td>
                    <td><?= htmlspecialchars($p['Course_Name']) ?></td>
                    <td><span class="badge-elite"><?= htmlspecialchars($p['Overall_Score']) ?>/100</span></td>
                    <td><?= htmlspecialchars(substr($p['Strong_Topics'], 0, 25)) ?>...</td>
                    <td style="font-family:'Space Mono'; font-weight:bold;"><?= htmlspecialchars($p['WhatsApp_Number']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5">No candidates found with these filters.</td></tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="card col-1">
        <h3 style="margin:0 0 15px 0; font-family:'Space Mono'; font-size: 16px;">Talent Pool Quality</h3>
        <canvas id="talentChart"></canvas>
    </div>

    <div class="card col-4">
        <h3 style="margin:0; font-family:'Space Mono';">All Candidate Records</h3>
        <div style="overflow-x:auto;">
            <table>
                <tr>
                    <th>Date</th>
                    <th>Name</th>
                    <th>ID</th>
                    <th>Course</th>
                    <th>Duration</th>
                    <th>Score</th>
                    <th>Weakness</th>
                    <th>Rank</th>
                </tr>
                <?php foreach ($all_rows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['Interview_Date']) ?></td>
                    <td><?= htmlspecialchars($row['Student_Name']) ?></td>
                    <td><?= htmlspecialchars($row['Student_ID']) ?></td>
                    <td><?= htmlspecialchars($row['Course_Name']) ?></td>
                    <td><?= htmlspecialchars($row['Interview_Duration_mins']) ?>m</td>
                    <td><?= htmlspecialchars($row['Overall_Score']) ?></td>
                    <td style="color: #666; font-size: 12px;"><?= htmlspecialchars(substr($row['Weak_Topics'], 0, 20)) ?>...</td>
                    <td>#<?= htmlspecialchars($row['Ranking_in_Batch']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>

<script>
// Talent Pool Chart (Pie)
const ctxTalent = document.getElementById('talentChart').getContext('2d');
new Chart(ctxTalent, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($tier_labels) ?>,
        datasets: [{
            data: <?= json_encode($tier_counts) ?>,
            backgroundColor: ['#98ff98', '#0a0a0a', '#ddd'],
            borderWidth: 0
        }]
    },
    options: {
        plugins: {
            legend: { position: 'bottom', labels: { font: { family: "'Manrope', sans-serif" } } }
        }
    }
});
</script>

</body>
</html>