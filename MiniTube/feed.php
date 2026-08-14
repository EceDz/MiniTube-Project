<?php
//20220702045 Ece Duzgec

// Home page showing subscribed videos, top channels and user profile

$servername = "localhost";
$dbuser = "root";
$dbpass = "mysql";
$dbname = "ece_duzgec";

$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$user_id = intval($_GET['user_id'] ?? 0);
if ($user_id <= 0) { header("Location: login.html"); exit; }

// Get logged in user info
$u = $conn->query("SELECT full_name, country, joined_on, bio, user_image FROM USERS WHERE user_id = $user_id")->fetch_assoc();
if (!$u) { header("Location: login.html"); exit; }

// Latest videos from subscribed channels
$videos_sql = "
 SELECT v.video_id, v.title, v.uploaded_at,
 v.description, v.view_count, v.like_count, v.duration_seconds,
 c.name AS channel_name, c.channel_id,
 u.country,
 DATEDIFF(CURDATE(), v.uploaded_at) AS days_ago,
 CASE
     WHEN v.view_count >= 1000 THEN 'Popular'
     WHEN v.view_count >= 100  THEN 'Trending'
     ELSE 'New'
 END AS popularity_badge
 FROM VIDEOS v
 JOIN CHANNELS c ON v.channel_id = c.channel_id
 JOIN USERS u ON c.owner_id = u.user_id
 JOIN SUBSCRIPTIONS s ON s.channel_id = c.channel_id
 WHERE s.subscriber_id = $user_id
 ORDER BY v.uploaded_at DESC
";
$videos_res = $conn->query($videos_sql);

// Top 5 channels by subscriber count
$top_sql = "
 SELECT c.channel_id, c.name, c.channel_image,
 COUNT(s.subscription_id) AS sub_count
 FROM CHANNELS c
 LEFT JOIN SUBSCRIPTIONS s ON s.channel_id = c.channel_id
 GROUP BY c.channel_id
 ORDER BY sub_count DESC
 LIMIT 5
";
$top_res = $conn->query($top_sql);
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
 <title>Hello, <?php echo htmlspecialchars($u['full_name']); ?>!</title>
 <style>
 * { box-sizing: border-box; margin: 0; padding: 0; }
 body {
 font-family: 'Segoe UI', sans-serif;
 background: url('feedspace.jpg') no-repeat center center fixed;
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
 .nav-links { display: flex; gap: 20px; align-items: center; flex-shrink: 0; }
 .nav-links a {
 color: #ccc; text-decoration: none; font-size: 0.95rem;
 transition: color 0.2s;
 }
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
 /* Layout of Page */
 .container {
 display: grid;
 grid-template-columns: 1fr 340px;
 gap: 24px;
 max-width: 1200px;
 margin: 28px auto;
 padding: 0 20px;
 }
 /* Cards */
 .card {
 background: rgba(0,0,0,0.6);
 border: 1px solid rgba(255,255,255,0.12);
 border-radius: 12px;
 padding: 24px;
 backdrop-filter: blur(8px);
 margin-bottom: 24px;
 }
 .card h2 {
 font-size: 1.1rem;
 font-weight: 700;
 color: #ff008caf;
 margin-bottom: 18px;
 padding-bottom: 10px;
 border-bottom: 1px solid rgba(255,255,255,0.1);
 text-transform: uppercase;
 letter-spacing: 1px;
 }
 /* Video Rows */
 .video-row {
 display: flex;
 flex-direction: column;
 gap: 4px;
 padding: 12px 0;
 border-bottom: 1px solid rgba(255,255,255,0.07);
 }
 .video-row:last-child { border-bottom: none; }
 .video-title a {
 color: #fff;
 text-decoration: none;
 font-weight: 600;
 font-size: 1rem;
 transition: color 0.2s;
 }
 .video-title a:hover { color: #ff008caf; }
   /* Video Popularity Badge */
 .pop-badge {
 display: inline-block;
 padding: 1px 8px;
 border-radius: 4px;
 font-size: 0.7rem;
 font-weight: 700;
 letter-spacing: 0.4px;
 vertical-align: middle;
 margin-left: 7px;
 }
.pop-badge.Popular  { background: #ff008caf; color: #fff; }
.pop-badge.Trending { background: rgb(0, 17, 255); color: #fff; }
.pop-badge.New      { background: rgba(238, 0, 0, 0.91); color: #ccc; }
 .video-desc {
 font-size: 0.82rem;
 color: #888;
 margin-top: 3px;
 white-space: nowrap;
 overflow: hidden;
 text-overflow: ellipsis;
 max-width: 600px;
 }
 .video-meta {
 font-size: 0.82rem;
 color: #aaa;
 display: flex;
 gap: 14px;
 flex-wrap: wrap;
 margin-top: 4px;
 }
 .video-meta span { display: flex; align-items: center; gap: 4px; }
 .channel-link a {
 color: #cc66ff;
 text-decoration: none;
 font-weight: 500;
 }
 .channel-link a:hover { color: #ff008caf; }
 .no-content {
 color: #888;
 font-size: 0.95rem;
 padding: 20px 0;
 text-align: center;
 }
 /* Top Channels */
 .top-channel {
 display: flex;
 align-items: center;
 gap: 12px;
 padding: 10px 0;
 border-bottom: 1px solid rgba(255,255,255,0.07);
 }
 .top-channel:last-child { border-bottom: none; }
 .top-channel img {
 width: 44px; height: 44px;
 border-radius: 50%;
 object-fit: cover;
 border: 2px solid #ff008caf;
 }
 .top-channel-info { flex: 1; }
 .top-channel-info a {
 color: #fff;
 text-decoration: none;
 font-weight: 600;
 font-size: 0.95rem;
 }
 .top-channel-info a:hover { color: #ff008caf; }
 .sub-count {
 font-size: 0.8rem;
 color: #aaa;
 }
 /* Profile */
 .profile-img {
 width: 72px; height: 72px;
 border-radius: 50%;
 object-fit: cover;
 border: 3px solid #ff008caf;
 margin-bottom: 12px;
 display: block;
 }
 .profile-name {
 font-size: 1.15rem;
 font-weight: 700;
 margin-bottom: 8px;
 }
 .profile-row {
 font-size: 0.88rem;
 color: #bbb;
 margin-bottom: 6px;
 }
 .profile-bio {
 font-size: 0.85rem;
 color: #999;
 margin-top: 10px;
 font-style: italic;
 border-top: 1px solid rgba(255,255,255,0.1);
 padding-top: 10px;
 }
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

 <!-- Left Side: Feed -->
 <div class="left">
 <div class="card">
 <h2>Your Feed</h2>
 <?php if ($videos_res && $videos_res->num_rows > 0): ?>
 <?php while ($v = $videos_res->fetch_assoc()): ?>
 <div class="video-row">
 <div class="video-title">
 <a href="watch.php?video_id=<?php echo $v['video_id']; ?>&user_id=<?php echo $user_id; ?>">
 <?php echo htmlspecialchars($v['title']); ?>
 </a>
 <span class="pop-badge <?php echo $v['popularity_badge']; ?>"><?php echo $v['popularity_badge']; ?></span>
 </div>
 <?php if ($v['description']): ?>
 <div class="video-desc"><?php echo htmlspecialchars($v['description']); ?></div>
 <?php endif; ?>
 <div class="video-meta">
 <span class="channel-link">
 <a href="channel.php?channel_id=<?php echo $v['channel_id']; ?>&user_id=<?php echo $user_id; ?>">
 <?php echo htmlspecialchars($v['channel_name']); ?>
 </a>
 </span>
 <span><?php echo formatDuration($v['duration_seconds']); ?></span>
 <span><?php echo number_format($v['view_count']); ?> views</span>
 <span><?php echo number_format($v['like_count']); ?> likes</span>
 <span><?php echo date('d.m.Y', strtotime($v['uploaded_at'])); ?> · <?php echo timeAgo($v['days_ago']); ?></span>
 </div>
 </div>
 <?php endwhile; ?>
 <?php else: ?>
 <p class="no-content">You are not subscribed to any channels yet.<br>
 Visit a channel page and hit Subscribe!</p>
 <?php endif; ?>
 </div>
 </div>

 <!-- Right Side: Top channels and Profile -->
 <div class="right">
 <div class="card">
 <h2>Top Channels</h2>
 <?php while ($ch = $top_res->fetch_assoc()): ?>
 <div class="top-channel">
 <a href="channel.php?channel_id=<?php echo $ch['channel_id']; ?>&user_id=<?php echo $user_id; ?>">
 <img src="<?php echo htmlspecialchars($ch['channel_image']); ?>"
 alt="<?php echo htmlspecialchars($ch['name']); ?>"
 onerror="this.src='https://picsum.photos/seed/default/200/200'"/>
 </a>
 <div class="top-channel-info">
 <a href="channel.php?channel_id=<?php echo $ch['channel_id']; ?>&user_id=<?php echo $user_id; ?>">
 <?php echo htmlspecialchars($ch['name']); ?>
 </a>
 <div class="sub-count"><?php echo number_format($ch['sub_count']); ?> subscribers</div>
 </div>
 </div>
 <?php endwhile; ?>
 </div>

 <div class="card">
 <h2>Your Profile</h2>
 <img class="profile-img"
 src="<?php echo htmlspecialchars($u['user_image']); ?>"
 alt="Profile"
 onerror="this.src='https://i.pravatar.cc/150?img=1'"/>
 <div class="profile-name"><?php echo htmlspecialchars($u['full_name']); ?></div>
 <div class="profile-row"> <?php echo htmlspecialchars($u['country']); ?></div>
 <div class="profile-row"> Joined <?php echo date('d.m.Y', strtotime($u['joined_on'])); ?></div>
 <?php if ($u['bio']): ?>
 <div class="profile-bio"><?php echo htmlspecialchars($u['bio']); ?></div>
 <?php endif; ?>
 </div>
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
