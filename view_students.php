<?php
session_start();
require 'db.php'; // make sure $conn (mysqli) is set

// Only logged-in class owners can access
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'class'){
    header("Location: index.html");
    exit;
}

$owner_name = $_SESSION['owner_name'] ?? 'Unknown';

// Filters
$search_name   = $_GET['search_name'] ?? "";
$search_course = $_GET['search_course'] ?? "";
$search_date   = $_GET['search_date'] ?? "";

// Build main query safely
$query = "SELECT * FROM students WHERE owner_name = ?";
$params = [$owner_name];
$types = "s";

if ($search_name !== "") {
    $query .= " AND Student_Name LIKE ?";
    $params[] = "%$search_name%";
    $types .= "s";
}
if ($search_course !== "") {
    $query .= " AND Course_Name LIKE ?";
    $params[] = "%$search_course%";
    $types .= "s";
}
if ($search_date !== "") {
    $query .= " AND Interview_Date = ?";
    $params[] = $search_date;
    $types .= "s";
}

$query .= " ORDER BY Interview_Date DESC, Student_Name ASC";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Collect data for visualizations
$rows = [];
$students = [];       // names
$scores = [];         // latest overall scores matching filter
$datesScores = [];    // date => [scores] for trend
$strongTopicsAll = [];
$weakTopicsAll = [];
$attendance_yes = 0;
$attendance_no = 0;
$student_latest_scores = []; // student => latest score (first appearance)
$student_previous_scores = []; // student => previous score (second appearance)
$student_rows_by_name = []; // for building modal data

while ($r = $result->fetch_assoc()) {
    $rows[] = $r;
    $name = $r['Student_Name'] ?? 'Unknown';
    $score = isset($r['Overall_Score']) && $r['Overall_Score'] !== "" ? (float)$r['Overall_Score'] : 0;
    $date = $r['Interview_Date'] ?? '';

    // for trend
    if ($date !== '') {
        if (!isset($datesScores[$date])) $datesScores[$date] = [];
        $datesScores[$date][] = $score;
    }

    // track first (latest) and second (previous) scores per student (rows are ordered by date desc)
    if (!isset($student_latest_scores[$name])) {
        $student_latest_scores[$name] = $score;
        $student_previous_scores[$name] = null;
    } else if ($student_previous_scores[$name] === null) {
        $student_previous_scores[$name] = $score;
    }

    // unique students list for chart order (keep first/latest encountered)
    if (!in_array($name, $students)) {
        $students[] = $name;
        $scores[] = $score;
    }

    // topics
    if (!empty($r['Strong_Topics'])) {
        foreach (explode(",", $r['Strong_Topics']) as $t) {
            $t = trim($t);
            if ($t !== '') $strongTopicsAll[] = $t;
        }
    }
    if (!empty($r['Weak_Topics'])) {
        foreach (explode(",", $r['Weak_Topics']) as $t) {
            $t = trim($t);
            if ($t !== '') $weakTopicsAll[] = $t;
        }
    }

    // attendance
    $att = strtolower(trim($r['Interview_Attended'] ?? ''));
    if ($att === 'yes' || $att === '1' || $att === 'true') $attendance_yes++;
    else $attendance_no++;

    // store row(s) by student for modal / profile (collect a few recent)
    if (!isset($student_rows_by_name[$name])) $student_rows_by_name[$name] = [];
    $student_rows_by_name[$name][] = $r;
}
$stmt->close();

// If no rows fetched, ensure arrays exist
if (!isset($rows)) $rows = [];

// Analytics computations
$totalCalls = count($rows);
$totalStudentsCalled = count(array_unique(array_map('strval', array_map(function($s){ return $s; }, $students))));

/* Top performers (latest score) */
$topPerformers = $student_latest_scores;
arsort($topPerformers);
$top5 = array_slice($topPerformers, 0, 5, true);

/* Weak performers (lowest latest score) */
$weakPerformers = $student_latest_scores;
asort($weakPerformers);
$weak5 = array_slice($weakPerformers, 0, 5, true);

/* Topic frequency */
$strongCount = array_count_values($strongTopicsAll);
$weakCount = array_count_values($weakTopicsAll);
arsort($strongCount);
arsort($weakCount);

/* Average score by date (for line chart) */
ksort($datesScores);
$trendLabels = [];
$trendAverages = [];
foreach ($datesScores as $d => $arrScores) {
    $trendLabels[] = $d;
    $trendAverages[] = count($arrScores) ? array_sum($arrScores)/count($arrScores) : 0;
}

/* Subject (Course) wise average score */
$courseScores = []; // course => [scores]
foreach ($rows as $r) {
    $c = $r['Course_Name'] ?? 'Unknown';
    $s = isset($r['Overall_Score']) && $r['Overall_Score'] !== '' ? (float)$r['Overall_Score'] : 0;
    if (!isset($courseScores[$c])) $courseScores[$c] = [];
    $courseScores[$c][] = $s;
}
$courseAvg = [];
foreach ($courseScores as $c => $arr) {
    $courseAvg[$c] = count($arr) ? array_sum($arr)/count($arr) : 0;
}
arsort($courseAvg);

/* Prepare JSON-safe arrays for JS */
$js_students = json_encode(array_values($students), JSON_UNESCAPED_UNICODE);
$js_scores = json_encode(array_values($scores), JSON_NUMERIC_CHECK);
$js_trendLabels = json_encode($trendLabels);
$js_trendAverages = json_encode($trendAverages);
$js_top5_names = json_encode(array_keys($top5), JSON_UNESCAPED_UNICODE);
$js_top5_scores = json_encode(array_values($top5), JSON_NUMERIC_CHECK);
$js_course_labels = json_encode(array_keys($courseAvg), JSON_UNESCAPED_UNICODE);
$js_course_values = json_encode(array_values($courseAvg), JSON_NUMERIC_CHECK);
$js_strong_topics = json_encode(array_keys($strongCount), JSON_UNESCAPED_UNICODE);
$js_strong_counts = json_encode(array_values($strongCount), JSON_NUMERIC_CHECK);
$js_weak_topics = json_encode(array_keys($weakCount), JSON_UNESCAPED_UNICODE);
$js_weak_counts = json_encode(array_values($weakCount), JSON_NUMERIC_CHECK);

/* For student profile panel: build a small dataset keyed by name */
$profile_data = [];
foreach ($student_rows_by_name as $name => $rlist) {
    // latest row is first element (we fetched ordered desc)
    $latest = $rlist[0];
    $prev = isset($rlist[1]) ? $rlist[1] : null;
    $profile_data[$name] = [
        'latest' => $latest,
        'previous' => $prev,
        'growth' => (isset($latest['Overall_Score']) && isset($prev['Overall_Score'])) ? ((float)$latest['Overall_Score'] - (float)$prev['Overall_Score']) : null
    ];
}
$js_profiles = json_encode($profile_data, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE);

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Students Dashboard — <?= htmlspecialchars($owner_name) ?></title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Manrope',sans-serif;background:#f5f5f0;color:#0a0a0a;padding:24px}
.container{max-width:1400px;margin:0 auto}
.card{background:#fff;border:2px solid #0a0a0a;border-radius:12px;padding:18px;margin-bottom:18px;box-shadow:6px 6px 0 #0a0a0a}
.header{display:flex;justify-content:space-between;align-items:center;gap:10px}
.header h2{font-family:'Space Mono',monospace}
.controls{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px}
input,select{padding:10px;border-radius:10px;border:2px solid #0a0a0a}
button{padding:10px 16px;border-radius:30px;border:2px solid #0a0a0a;background:#98ff98;cursor:pointer;font-weight:700}
.kpis{display:flex;gap:12px;flex-wrap:wrap;margin-top:12px}
.kpi{background:#fff;border:2px solid #000;padding:12px;border-radius:10px;min-width:170px}
.kpi .num{font-size:1.8rem;font-family:'Space Mono',monospace}
.topics{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
.tag{background:#000;color:#98ff98;padding:6px 10px;border-radius:8px;font-size:13px}
.tag.bad{background:#b30000;color:#fff}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
@media(max-width:980px){.grid{grid-template-columns:1fr;}}
.table{overflow:auto}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #ddd;text-align:left}
th{background:#0a0a0a;color:#fff}
tr:hover td{background:#f0f0f0;cursor:pointer}
.profile-panel{position:fixed;right:20px;top:80px;width:360px;background:#fff;border:2px solid #0a0a0a;border-radius:12px;padding:12px;box-shadow:8px 8px 0 #0a0a0a;display:none;z-index:999}
.profile-panel h4{font-family:'Space Mono',monospace;margin-bottom:8px}
.small{font-size:0.9rem;color:#555}
</style>
</head>
<body>
<div class="container">

    <div class="card header">
        <div>
            <h2>Students of <?= htmlspecialchars($owner_name) ?></h2>
            <div class="small">Click any row to open student profile</div>
        </div>
        <div>
            <button onclick="window.print()" class="btn"></i> Save Report</button>
            <a href="dashboard_class.php"><button>⬅ Dashboard</button></a>
        </div>
    </div>

    <div class="card">
        <form method="GET" class="controls" onsubmit="">
            <input type="text" name="search_name" placeholder="Student name" value="<?= htmlspecialchars($search_name) ?>">
            <input type="text" name="search_course" placeholder="Course name" value="<?= htmlspecialchars($search_course) ?>">
            <input type="date" name="search_date" value="<?= htmlspecialchars($search_date) ?>">
            <button type="submit">Apply</button>
            <a href="view_students.php"><button type="button">Reset</button></a>
        </form>

        <div class="kpis">
            <div class="kpi">
                <div>Total Calls</div>
                <div class="num"><?= $totalCalls ?></div>
            </div>
            <div class="kpi">
                <div>Students Attended</div>
                <div class="num"><?= $attendance_yes ?></div>
                <div class="small">Not attended: <?= $attendance_no ?></div>
            </div>
            <div class="kpi">
                <div>Unique Students</div>
                <div class="num"><?= $totalStudentsCalled ?></div>
            </div>
            <div class="kpi">
                <div>Top Avg Score</div>
                <div class="num"><?= count($scores) ? max($scores) : 0 ?></div>
            </div>
            <div class="kpi">
                <div>Avg Score (Filtered)</div>
                <div class="num"><?= count($scores) ? round(array_sum($scores)/count($scores),2) : 0 ?></div>
            </div>
        </div>

        <h4 style="margin-top:12px">Collective Strong Topics</h4>
        <div class="topics">
            <?php foreach(array_slice($strongCount,0,12) as $t => $c): ?>
                <span class="tag"><?= htmlspecialchars($t) ?> (<?= $c ?>)</span>
            <?php endforeach; ?>
        </div>

        <h4 style="margin-top:12px">Collective Weak Topics</h4>
        <div class="topics">
            <?php foreach(array_slice($weakCount,0,12) as $t => $c): ?>
                <span class="tag bad"><?= htmlspecialchars($t) ?> (<?= $c ?>)</span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="grid" style="margin-bottom:12px">
        <div class="card">
            <h4>Attendance (Yes / No)</h4>
            <canvas id="attendancePie"></canvas>
        </div>

        <div class="card">
            <h4>Avg Score Over Time</h4>
            <canvas id="avgLine"></canvas>
        </div>

        <div class="card">
            <h4>Top 5 Performers</h4>
            <canvas id="topBar"></canvas>
        </div>
    </div>

    <div class="grid" style="margin-bottom:12px">
        <div class="card">
            <h4>Course-wise Avg Score</h4>
            <canvas id="courseDonut"></canvas>
        </div>

        <div class="card">
            <h4>Most Common Strong Topics (count)</h4>
            <canvas id="strongDonut"></canvas>
        </div>

        <div class="card">
            <h4>Most Common Weak Topics (count)</h4>
            <canvas id="weakDonut"></canvas>
        </div>
    </div>

    <!-- Student table -->
    <div class="card table">
        <h4>All Student Records (click row for profile)</h4>
        <table id="studentsTable">
            <thead>
            <tr>
                <th>Date</th>
                <th>Student Name</th>
                <th>Course</th>
                <th>Overall Score</th>
                <th>Strong Topics</th>
                <th>Weak Topics</th>
                <th>Ranking</th>
                <th>Attended</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($rows)): foreach ($rows as $r): ?>
                <tr data-name="<?= htmlspecialchars($r['Student_Name']) ?>">
                    <td><?= htmlspecialchars($r['Interview_Date'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['Student_Name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['Course_Name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['Overall_Score'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['Strong_Topics'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['Weak_Topics'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['Ranking_in_Batch'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['Interview_Attended'] ?? '') ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="8">No records found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Student profile panel -->
<div class="profile-panel" id="profilePanel">
    <button id="closeProfile" style="float:right;border:none;background:#ff6b6b;padding:6px 8px;border-radius:8px;cursor:pointer">Close</button>
    <h4 id="profileName">Student Name</h4>
    <div id="profileMeta" class="small"></div>

    <div style="margin-top:8px">
        <canvas id="profileRadar" style="max-height:260px"></canvas>
    </div>

    <div style="margin-top:10px">
        <div><strong>Latest Score:</strong> <span id="latestScore"></span></div>
        <div><strong>Previous Score:</strong> <span id="prevScore"></span></div>
        <div><strong>Growth:</strong> <span id="growthScore"></span></div>
        <div style="margin-top:8px"><strong>Strong Topics:</strong> <div id="profileStrong"></div></div>
        <div style="margin-top:8px"><strong>Weak Topics:</strong> <div id="profileWeak"></div></div>
    </div>
</div>

<script>
// --- Data injected from PHP
const studentsJS = <?= $js_students ?>;
const scoresJS = <?= $js_scores ?>;
const trendLabels = <?= $js_trendLabels ?>;
const trendAverages = <?= $js_trendAverages ?>;
const topNames = <?= $js_top5_names ?>;
const topScores = <?= $js_top5_scores ?>;
const courseLabels = <?= $js_course_labels ?>;
const courseValues = <?= $js_course_values ?>;
const strongTopics = <?= $js_strong_topics ?>;
const strongCounts = <?= $js_strong_counts ?>;
const weakTopics = <?= $js_weak_topics ?>;
const weakCounts = <?= $js_weak_counts ?>;
const profiles = <?= $js_profiles ?>;
const attendanceYes = <?= json_encode($attendance_yes) ?>;
const attendanceNo  = <?= json_encode($attendance_no) ?>;

// --- Attendance pie
new Chart(document.getElementById('attendancePie'), {
    type: 'pie',
    data: {
        labels: ['Attended','Not Attended'],
        datasets: [{ data: [attendanceYes, attendanceNo], backgroundColor: ['#98ff98','#ff6b6b'], borderColor: '#0a0a0a', borderWidth: 1 }]
    },
    options: { responsive:true, plugins:{legend:{position:'bottom'}} }
});

// --- Trend line (avg score over dates)
new Chart(document.getElementById('avgLine'), {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [{
            label: 'Avg Score',
            data: trendAverages,
            borderColor:'#667eea',
            backgroundColor:'rgba(102,117,234,0.15)',
            tension:0.3,
            fill:true,
            pointRadius:4
        }]
    },
    options: { scales:{y:{beginAtZero:true,max:100}}, plugins:{legend:{display:false}} }
});

// --- Top performers bar
new Chart(document.getElementById('topBar'), {
    type:'bar',
    data:{ labels: topNames, datasets:[{label:'Score', data: topScores, backgroundColor:'#98ff98', borderColor:'#0a0a0a', borderWidth:1}] },
    options:{ indexAxis:'y', scales:{x:{beginAtZero:true,max:100}} }
});

// --- Course donut
new Chart(document.getElementById('courseDonut'), {
    type:'doughnut',
    data:{ labels: courseLabels, datasets:[{ data: courseValues, backgroundColor: ['#98ff98','#667eea','#ccc','#ffb86b','#b3b3b3'], borderWidth:1 }] },
    options:{plugins:{legend:{position:'bottom'}}}
});

// --- Strong topics donut
new Chart(document.getElementById('strongDonut'), {
    type:'doughnut',
    data:{ labels: strongTopics, datasets:[{ data: strongCounts.slice(0,12), backgroundColor: Array.from({length:12},(_,i)=>'#98ff98'), borderWidth:1 }] },
    options:{plugins:{legend:{display:false}}}
});

// --- Weak topics donut
new Chart(document.getElementById('weakDonut'), {
    type:'doughnut',
    data:{ labels: weakTopics, datasets:[{ data: weakCounts.slice(0,12), backgroundColor: Array.from({length:12},(_,i)=>'#ff6b6b'), borderWidth:1 }] },
    options:{plugins:{legend:{display:false}}}
});

// --- Student table click -> profile panel
const table = document.getElementById('studentsTable');
const panel = document.getElementById('profilePanel');
const closeBtn = document.getElementById('closeProfile');
const profileName = document.getElementById('profileName');
const profileMeta = document.getElementById('profileMeta');
const latestScoreEl = document.getElementById('latestScore');
const prevScoreEl = document.getElementById('prevScore');
const growthEl = document.getElementById('growthScore');
const profileStrong = document.getElementById('profileStrong');
const profileWeak = document.getElementById('profileWeak');
let radarChart = null;

table.addEventListener('click', (ev) => {
    // find row
    let tr = ev.target.closest('tr');
    if (!tr || !tr.dataset.name) return;
    const name = tr.dataset.name;
    openProfile(name);
});

closeBtn.addEventListener('click', () => panel.style.display = 'none');

function openProfile(name) {
    const data = profiles[name];
    if (!data) {
        alert('No profile data for ' + name);
        return;
    }
    panel.style.display = 'block';
    profileName.textContent = name;
    const latest = data.latest || {};
    const prev = data.previous || {};
    profileMeta.textContent = (latest.Course_Name ? latest.Course_Name + ' • ' : '') + (latest.Interview_Date || '');
    latestScoreEl.textContent = latest.Overall_Score ?? 'N/A';
    prevScoreEl.textContent = prev.Overall_Score ?? 'N/A';
    growthEl.textContent = (data.growth === null ? 'N/A' : (data.growth > 0 ? '+' : '') + data.growth.toFixed(2));
    // strong/weak topics lists
    profileStrong.innerHTML = '';
    profileWeak.innerHTML = '';
    if (latest.Strong_Topics) {
        latest.Strong_Topics.split(',').forEach(t => {
            if (t.trim()!=='') profileStrong.innerHTML += '<span class="tag" style="margin-right:6px;margin-bottom:6px">'+t.trim()+'</span>';
        });
    }
    if (latest.Weak_Topics) {
        latest.Weak_Topics.split(',').forEach(t => {
            if (t.trim()!=='') profileWeak.innerHTML += '<span class="tag bad" style="margin-right:6px;margin-bottom:6px">'+t.trim()+'</span>';
        });
    }

    // Radar chart data: create a small radar using counts of strong/weak topics
    // Build labels = union of strong+weak topics for this student, values = strong count (1) and weak negative? we'll simply plot two datasets
    const sArr = latest.Strong_Topics ? latest.Strong_Topics.split(',').map(x=>x.trim()).filter(x=>x) : [];
    const wArr = latest.Weak_Topics ? latest.Weak_Topics.split(',').map(x=>x.trim()).filter(x=>x) : [];
    const labels = Array.from(new Set([...sArr, ...wArr]));
    const strongVals = labels.map(l => sArr.includes(l) ? 1 : 0);
    const weakVals = labels.map(l => wArr.includes(l) ? 1 : 0);

    // create / update radar chart
    const ctx = document.getElementById('profileRadar').getContext('2d');
    if (radarChart) radarChart.destroy();
    radarChart = new Chart(ctx, {
        type:'radar',
        data:{
            labels: labels,
            datasets:[
                { label:'Strong', data: strongVals, fill:true, backgroundColor:'rgba(152,255,152,0.2)', borderColor:'#0a0a0a' },
                { label:'Weak', data: weakVals, fill:true, backgroundColor:'rgba(255,107,107,0.2)', borderColor:'#b30000' }
            ]
        },
        options:{ scales:{ r:{ beginAtZero:true, ticks:{ stepSize:1 } } }, plugins:{legend:{position:'bottom'}} }
    });
}
</script>
</body>
</html>
