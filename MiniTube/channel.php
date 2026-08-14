<?php
//20220702045 Ece Duzgec

// Channel page with info, subscribe button and list of videos from the channel.

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$servername = "localhost";
$dbuser = "root";
$dbpass = "mysql";
$dbname = "ece_duzgec";

$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$user_id = intval($_GET['user_id'] ?? 0);
$channel_id = intval($_GET['channel_id'] ?? 0);
if ($user_id <= 0 || $channel_id <= 0) { header("Location: login.html"); exit; }

// Handle subscribe / unsubscribe via GET
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'subscribe') {
    $stmt = $conn->prepare("INSERT IGNORE INTO SUBSCRIPTIONS (subscriber_id, channel_id, subscribed_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $user_id, $channel_id);
    $stmt->execute();
    $stmt->close();
    } 
    elseif ($_GET['action'] === 'unsubscribe') {
    $stmt = $conn->prepare("DELETE FROM SUBSCRIPTIONS WHERE subscriber_id = ? AND channel_id = ?");
    $stmt->bind_param("ii", $user_id, $channel_id);
    $stmt->execute();
    $stmt->close();
 }
 header("Location: channel.php?channel_id=$channel_id&user_id=$user_id");
 exit;
}

// Channel info
$ch_sql = "
 SELECT c.*, u.full_name AS owner_name, u.country AS owner_country,
 (SELECT COUNT(*) FROM SUBSCRIPTIONS WHERE channel_id = c.channel_id) AS sub_count
 FROM CHANNELS c
 JOIN USERS u ON c.owner_id = u.user_id
 WHERE c.channel_id = $channel_id
";
$ch = $conn->query($ch_sql)->fetch_assoc();
if (!$ch) { echo "Channel not found."; exit; }

// Check if user is subscribed
$sub_check = $conn->query("SELECT 1 FROM SUBSCRIPTIONS WHERE subscriber_id = $user_id AND channel_id = $channel_id");
$is_subscribed = $sub_check->num_rows > 0;

// All videos by this channel, newest first
$vids_res = $conn->query("
 SELECT video_id, title, duration_seconds, uploaded_at, view_count, like_count,
        DATEDIFF(CURDATE(), uploaded_at) AS days_ago
 FROM VIDEOS
 WHERE channel_id = $channel_id
 ORDER BY uploaded_at DESC
");

// Format duration helper
function formatDuration($secs) {
 $h = floor($secs / 3600);
 $m = floor(($secs % 3600) / 60);
 $s = $secs % 60;
 if ($h > 0) return sprintf('%d:%02d:%02d', $h, $m, $s);
    return sprintf('%d:%02d', $m, $s);
}

// Returns a day count of videos
function timeAgo($days) {
 $days = intval($days);
 if ($days <= 0)  return 'Today';
    if ($days === 1) return '1 day ago';
    return "$days days ago";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
 <meta charset="UTF-8"/>
 <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
 <title><?php echo htmlspecialchars($ch['name']); ?> — MiniTube</title>
 <style>
 * { box-sizing: border-box; margin: 0; padding: 0; }
 body {
 font-family: 'Segoe UI', sans-serif;
 background: url('channelspace.jpg') no-repeat center center fixed;
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
 .nav-links { display: flex; gap: 20px; flex-shrink: 0; }
 .nav-links a { color: #ccc; text-decoration: none; font-size: 0.95rem; }
 .nav-links a:hover { color: #ff008caf; }
 .nav-search { flex: 1; max-width: 480px; display: flex; gap: 0; }
 .nav-search input {
 flex: 1; padding: 8px 14px;
 border-radius: 8px 0 0 8px;
 border: 1px solid rgba(255,255,255,0.2); border-right: none;
 background: rgba(255,255,255,0.1); color: #fff; font-size: 0.9rem; outline: none;
 }
 .nav-search input::placeholder { color: #aaa; }
 .nav-search input:focus { border-color: #ff008caf; background: rgba(255,255,255,0.15); }
 .nav-search button {
 padding: 8px 16px; background: #ff008caf; color: #fff;
 border: none; border-radius: 0 8px 8px 0;
 font-size: 0.9rem; font-weight: 700; cursor: pointer;
 }
 .nav-search button:hover { background: #d4006a; }
 .container { max-width: 900px; margin: 28px auto; padding: 0 20px; }
 .card {
 background: rgba(0,0,0,0.6);
 border: 1px solid rgba(255,255,255,0.12);
 border-radius: 12px;
 padding: 28px;
 backdrop-filter: blur(8px);
 margin-bottom: 24px;
 }
 /* Channel header */
 .ch-header {
 display: flex;
 gap: 24px;
 align-items: flex-start;
 margin-bottom: 20px;
 }
 .ch-header img {
 width: 100px; height: 100px;
 border-radius: 50%;
 object-fit: cover;
 border: 3px solid #ff008caf;
 flex-shrink: 0;
 }
 .ch-info h1 {
 font-size: 1.8rem;
 font-weight: 800;
 margin-bottom: 6px;
 }
 .ch-meta {
 display: flex;
 flex-wrap: wrap;
 gap: 14px;
 font-size: 0.88rem;
 color: #bbb;
 margin-bottom: 10px;
 }
 .ch-desc {
 font-size: 0.9rem;
 color: #ccc;
 font-style: italic;
 }
  /* Subscription Button */
 .sub-btn {
 padding: 10px 28px;
 border: none;
 border-radius: 6px;
 font-size: 1rem;
 font-weight: 700;
 cursor: pointer;
 text-decoration: none;
 display: inline-block;
 transition: background 0.2s;
 }
 .sub-btn.subscribe { background: #ff008caf; color: #fff; }
 .sub-btn.subscribe:hover { background: #ff2a9baf; }
 .sub-btn.unsubscribe { background: rgba(255,255,255,0.15); color: #fff; border: 1px solid rgba(255,255,255,0.3); }
 .sub-btn.unsubscribe:hover { background: rgba(255,0,0,0.3); }
 /* Videos table */
 .card h2 {
 font-size: 1rem;
 font-weight: 700;
 color: #ff008caf;
 margin-bottom: 16px;
 text-transform: uppercase;
 letter-spacing: 1px;
 padding-bottom: 10px;
 border-bottom: 1px solid rgba(255,255,255,0.1);
 }
 table { width: 100%; border-collapse: collapse; }
 th {
 text-align: left;
 font-size: 0.8rem;
 color: #888;
 text-transform: uppercase;
 letter-spacing: 1px;
 padding: 8px 10px;
 border-bottom: 1px solid rgba(255,255,255,0.1);
 }
 td {
 padding: 12px 10px;
 border-bottom: 1px solid rgba(255,255,255,0.06);
 font-size: 0.92rem;
 color: #ddd;
 vertical-align: middle;
 }
 tr:last-child td { border-bottom: none; }
 td a { color: #fff; text-decoration: none; font-weight: 600; }
 td a:hover { color: #ff008caf; }
 .badge-views { color: #aaa; }
 .no-videos { color: #888; text-align: center; padding: 20px 0; }
 </style>
</head>
<body>

<nav>
 <a class="nav-logo" href="feed.php?user_id=<?php echo $user_id; ?>">
 <svg viewBox="0 0 90 90" xmlns="http://www.w3.org/2000/svg">
 <rect width="90" height="90" rx="20" fill="#ff008caf"/>
 <polygon points="35,25 65,45 35,65" fill="#fff"/>
 </svg>
 <span>Mini<em>Tube</em></span>
 </a>
 <form class="nav-search" action="search.php" method="GET">
 <input type="hidden" name="user_id" value="<?php echo $user_id; ?>"/>
 <input type="text" name="q" placeholder="Search videos and channels…"/>
 <button type="submit">Search</button>
 </form>
 <div class="nav-links">
 <a href="feed.php?user_id=<?php echo $user_id; ?>">Home</a>
 <a href="sql.html?user_id=<?php echo $user_id; ?>">SQL</a>
 <a href="login.html">Logout</a>
 </div>
</nav>

<div class="container">

 <div class="card">
 <div class="ch-header">
 <img src="<?php echo htmlspecialchars($ch['channel_image']); ?>"
 alt="<?php echo htmlspecialchars($ch['name']); ?>"
 onerror="this.src='https://picsum.photos/seed/default/200/200'"/>
 <div class="ch-info">
 <h1><?php echo htmlspecialchars($ch['name']); ?></h1>
 <div class="ch-meta">
 <span>️ <?php echo htmlspecialchars($ch['category']); ?></span>
 <span> <?php echo htmlspecialchars($ch['owner_name']); ?></span>
 <span> <?php echo htmlspecialchars($ch['owner_country']); ?></span>
 <span> Created <?php echo date('d.m.Y', strtotime($ch['created_on'])); ?></span>
 <span> <?php echo number_format($ch['sub_count']); ?> subscribers</span>
 </div>
 <p class="ch-desc">
 <?php echo $ch['description'] ? htmlspecialchars($ch['description']) : '<em style="color:#666">(no description)</em>'; ?>
 </p>
 </div>
 </div>

 <?php
 $action = $is_subscribed ? 'unsubscribe' : 'subscribe';
 $label = $is_subscribed ? 'Unsubscribe' : 'Subscribe';
 $cls = $is_subscribed ? 'unsubscribe' : 'subscribe';
 ?>
 <a href="channel.php?channel_id=<?php echo $channel_id; ?>&user_id=<?php echo $user_id; ?>&action=<?php echo $action; ?>"
 class="sub-btn <?php echo $cls; ?>">
 <?php echo $label; ?>
 </a>
 </div>

 <div class="card">
 <h2>Videos</h2>
 <?php if ($vids_res && $vids_res->num_rows > 0): ?>
 <table>
 <thead>
 <tr>
 <th>Title</th>
 <th>Duration</th>
 <th>Uploaded</th>
 <th>Views</th>
 <th>Likes</th>
 </tr>
 </thead>
 <tbody>
 <?php while ($v = $vids_res->fetch_assoc()): ?>
 <tr>
 <td>
 <a href="watch.php?video_id=<?php echo $v['video_id']; ?>&user_id=<?php echo $user_id; ?>">
 <?php echo htmlspecialchars($v['title']); ?>
 </a>
 </td>
 <td><?php echo formatDuration($v['duration_seconds']); ?></td>
 <td><?php echo date('d.m.Y', strtotime($v['uploaded_at'])); ?><br><span style="font-size:0.78rem;color:#888;"><?php echo timeAgo($v['days_ago']); ?></span></td>
 <td class="badge-views"><?php echo number_format($v['view_count']); ?></td>
 <td class="badge-views"><?php echo number_format($v['like_count']); ?></td>
 </tr>
 <?php endwhile; ?>
 </tbody>
 </table>
 <?php else: ?>
 <p class="no-videos">No videos uploaded yet.</p>
 <?php endif; ?>
 </div>

</div>
<script>
window.addEventListener('pageshow', function(e) {
    if (e.persisted) window.location.reload();
});
</script>
</body>
</html>
<?php $conn->close(); ?>
