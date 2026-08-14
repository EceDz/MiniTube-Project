<?php
//20220702045 Ece Duzgec

// Executes SELECT / INSERT / UPDATE / DELETE queries and renders results or affected rows.

$servername = "localhost";
$dbuser = "root";
$dbpass = "mysql";
$dbname = "ece_duzgec";

mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$query   = trim($_POST['query'] ?? '');
$user_id = intval($_POST['user_id'] ?? $_GET['user_id'] ?? 0);
$result_html = '';

// Shows MySQL error.
function showError(string $e): string {
    if (preg_match("/Unknown column '(.+?)' in '(.+?)'/i", $e, $m))
        return "Column <strong>{$m[1]}</strong> does not exist in {$m[2]}. Check with <code>DESCRIBE &lt;table&gt;;</code>.";
    if (preg_match("/Table '.+?\.(.+?)' doesn't exist/i", $e, $m))
        return "Table <strong>{$m[1]}</strong> does not exist. Run <code>SHOW TABLES;</code> to see available tables.";
    if (stripos($e, 'Column count doesn') !== false)
        return "Column count does not match value count. Check your INSERT columns and values.";
    if (preg_match("/Duplicate entry '(.+?)' for key/i", $e, $m))
        return "Duplicate entry <strong>{$m[1]}</strong> — this value already exists.";
    if (preg_match("/You have an error in your SQL syntax.+near '(.{0,40})'/i", $e, $m))
        return "SQL syntax error near <strong>&ldquo;" . htmlspecialchars($m[1]) . "&rdquo;</strong>. Check quotes, commas, or keywords.";
    if (stripos($e, 'foreign key constraint') !== false)
        return "Foreign key constraint failed — referenced row does not exist or is still in use.";
    return "MySQL error: " . htmlspecialchars($e);
}

// Runs a SELECT and returns its result as a table.
function buildTable(mysqli $conn, string $sql): string {
    $res = $conn->query($sql);
    if (!$res || $res->num_rows === 0) return '';
    $fields = $res->fetch_fields();
    $html = '<div class="table-wrap"><table><thead><tr>';
    foreach ($fields as $f) $html .= '<th>' . htmlspecialchars($f->name) . '</th>';
    $html .= '</tr></thead><tbody>';
    while ($row = $res->fetch_assoc()) {
        $html .= '<tr>';
        foreach ($row as $val) $html .= '<td>' . htmlspecialchars($val ?? 'NULL') . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table></div>';
    return $html;
}

if ($query !== '') {
    $type = strtoupper(strtok(ltrim($query), " \t\n"));

    // SELECT, show up to 10 result rows as a table.
    if ($type === 'SELECT') {
        $res = $conn->query($query);
        if ($res === FALSE) {
            $result_html = '<div class="msg error">' . showError($conn->error) . '</div>';
        } else {
            $fields = $res->fetch_fields();
            $rows = []; $count = 0;
            while ($count < 10 && $row = $res->fetch_assoc()) { $rows[] = $row; $count++; }
            if (empty($rows)) {
                $result_html = '<div class="msg info">Query returned no rows.</div>';
            } else {
                $result_html = '<div class="table-wrap"><table><thead><tr>';
                foreach ($fields as $f) $result_html .= '<th>' . htmlspecialchars($f->name) . '</th>';
                $result_html .= '</tr></thead><tbody>';
                foreach ($rows as $row) {
                    $result_html .= '<tr>';
                    foreach ($row as $val) $result_html .= '<td>' . htmlspecialchars($val ?? 'NULL') . '</td>';
                    $result_html .= '</tr>';
                }
                $result_html .= '</tbody></table></div>';
                if ($count === 10) $result_html .= '<p class="limit-note">Showing first 10 rows only.</p>';
            }
        }
    // INSERT / UPDATE / DELETE,  execute and display the affected row(s).
    } elseif (in_array($type, ['INSERT', 'UPDATE', 'DELETE'])) {
        if ($conn->query($query) === FALSE) {
            $result_html = '<div class="msg error">' . showError($conn->error) . '</div>';
        } else {
            $affected = $conn->affected_rows;
            preg_match('/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+(\w+)/i', $query, $tm);
            $table = $tm[1] ?? '';

            $row_html = '';
            if ($type === 'INSERT' && $table) {
                $new_id = $conn->insert_id;
                if ($new_id > 0) {
                    $pk_res = $conn->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
                    $pk_col = $pk_res ? ($pk_res->fetch_assoc()['Column_name'] ?? null) : null;
                    $fetch  = $pk_col
                        ? "SELECT * FROM `$table` WHERE `$pk_col` = $new_id"
                        : "SELECT * FROM `$table` ORDER BY 1 DESC LIMIT $affected";
                    $row_html = buildTable($conn, $fetch);
                }
            } elseif ($type === 'UPDATE' && $table) {
                if (preg_match('/WHERE\s+(.+?)(?:\s+ORDER|\s+LIMIT|;?\s*$)/is', $query, $wm))
                    $fetch = "SELECT * FROM `$table` WHERE {$wm[1]} LIMIT 10";
                else
                    $fetch = "SELECT * FROM `$table` LIMIT 10";
                $row_html = buildTable($conn, $fetch);
            }

            $label = $type === 'DELETE'
                ? "Deleted <strong>$affected</strong> row(s) from <strong>$table</strong>. Rows have been removed."
                : "<strong>$affected</strong> row(s) affected in <strong>$table</strong>:";

            $result_html  = '<div class="msg success" style="margin-bottom:14px;">' . $label . '</div>';
            $result_html .= $row_html ?: ($type === 'DELETE' ? '' : '<div class="msg info">No rows to display.</div>');
        }

    } else {
        $result_html = '<div class="msg error">Only SELECT, INSERT, UPDATE and DELETE queries are allowed.</div>';
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>SQL Console</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Segoe UI', sans-serif;
    background: url('sqlspace.jpg') no-repeat center center fixed;
    background-size: cover;
    color: #fff;
    min-height: 100vh;
}
 /* Navigation Panel */
nav {
    background: rgba(0,0,0,0.75);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(255,255,255,0.1);
    padding: 14px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky; top: 0; z-index: 100;
}
.nav-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; color: #fff; }
.nav-logo svg { width: 36px; height: 36px; }
.nav-logo span { font-size: 1.5rem; font-weight: 800; }
.nav-logo em { color: #ff008caf; font-style: normal; }
.nav-links { display: flex; gap: 20px; }
.nav-links a { color: #ccc; text-decoration: none; font-size: 0.95rem; }
.nav-links a:hover { color: #ff008caf; }
.container { max-width: 900px; margin: 28px auto; padding: 0 20px; }
.card {
    background: rgba(0,0,0,0.6);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 12px;
    padding: 28px;
    backdrop-filter: blur(8px);
    margin-bottom: 24px;
}
.card h2 {
    font-size: 1rem;
    font-weight: 700;
    color: #ff008caf;
    margin-bottom: 18px;
    text-transform: uppercase;
    letter-spacing: 1px;
}
textarea {
    width: 100%;
    padding: 14px;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 8px;
    color: #fff;
    font-size: 0.95rem;
    font-family: 'Courier New', monospace;
    resize: vertical;
    min-height: 120px;
    outline: none;
    transition: border 0.2s;
}
textarea:focus { border-color: #ff008caf; }
button[type=submit] {
    margin-top: 12px;
    padding: 11px 32px;
    background: #ff008caf;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s;
}
button[type=submit]:hover { background: #ff2a9baf; }
.query-echo {
    background: rgba(0,0,0,0.5);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    padding: 14px 18px;
    font-family: 'Courier New', monospace;
    font-size: 0.88rem;
    color: #cc66ff;
    margin-bottom: 18px;
    word-break: break-all;
    white-space: pre-wrap;
}
.table-wrap {
    overflow-x: auto;
    width: 100%;
}
table {
    min-width: 600px;
    border-collapse: collapse;
    font-size: 0.88rem;
}
th {
    background: rgba(255,0,140,0.2);
    padding: 10px 12px;
    text-align: left;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #ff008caf;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
td {
    padding: 10px 12px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    color: #ddd;
    max-width: 220px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
tr:hover td { background: rgba(255,255,255,0.04); }
.msg { padding: 14px 18px; border-radius: 6px; font-size: 0.95rem; }
.msg.error   { background: rgba(255,0,0,0.1);    border-left: 3px solid #ff4444; color: #ff8888; }
.msg.success { background: rgba(0,255,0,0.07);   border-left: 3px solid #44ff44; color: #88ff88; }
.msg.info    { background: rgba(255,255,255,0.05); border-left: 3px solid rgba(255,255,255,0.3); color: #aaa; }
.limit-note  { font-size: 0.8rem; color: #888; margin-top: 10px; }
</style>
</head>
<body>

<nav>
    <a class="nav-logo" href="sql.html">
        <svg viewBox="0 0 90 90" xmlns="http://www.w3.org/2000/svg">
            <rect width="90" height="90" rx="20" fill="#ff008caf"/>
            <polygon points="35,25 65,45 35,65" fill="#fff"/>
        </svg>
        <span>Mini<em>Tube</em></span>
    </a>
    <div class="nav-links">
        <a href="feed.php?user_id=<?php echo $user_id; ?>">Home</a>
        <a href="login.html">Logout</a>
    </div>
</nav>

<div class="container">
    <div class="card">
        <h2>SQL Console</h2>
        <form action="sql.php" method="POST">
            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
            <textarea name="query"><?php echo htmlspecialchars($query); ?></textarea>
            <button type="submit">Run Query</button>
        </form>
    </div>

    <?php if ($query !== ''): ?>
    <div class="card">
        <h2>Executed Query</h2>
        <div class="query-echo"><?php echo htmlspecialchars($query); ?></div>
        <h2>Result</h2>
        <?php echo $result_html; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
