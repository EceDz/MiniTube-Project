<?php
//20220702045 Ece Duzgec

// Search results page for videos and channels with All / Videos / Channels filters.

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$servername = "localhost";
$dbuser     = "root";
$dbpass     = "mysql";
$dbname     = "ece_duzgec";

$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$user_id = intval($_GET['user_id'] ?? 0);
if ($user_id <= 0) { header("Location: login.html"); exit; }

$u = $conn->query("SELECT full_name, user_image FROM USERS WHERE user_id = $user_id")->fetch_assoc();
if (!$u) { header("Location: login.html"); exit; }

$q     = trim($_GET['q'] ?? '');
$type  = $_GET['type'] ?? 'all';   

$video_results   = [];
$channel_results = [];

// Always run queries when $q is empty, all videos / channels are returned.
if ($type === 'all' || $type === 'videos') {
    $where = '';
    if ($q !== '') {
        $like  = '%' . $conn->real_escape_string($q) . '%';
        $where = "WHERE v.title LIKE '$like' OR v.description LIKE '$like'";
    }
    $res = $conn->query("
        SELECT v.video_id, v.title, v.description, v.view_count, v.like_count,
               v.duration_seconds, v.uploaded_at,
               c.channel_id, c.name AS channel_name,
               CASE
                   WHEN v.view_count >= 1000 THEN 'Popular'
                   WHEN v.view_count >= 100  THEN 'Trending'
                   ELSE 'New'
               END AS popularity_badge
        FROM   VIDEOS v
        JOIN   CHANNELS c ON c.channel_id = v.channel_id
        $where
        ORDER BY v.view_count DESC
        LIMIT 200
    ");
    while ($row = $res->fetch_assoc()) $video_results[] = $row;
}

if ($type === 'all' || $type === 'channels') {
    $where = '';
    if ($q !== '') {
        $like  = '%' . $conn->real_escape_string($q) . '%';
        $where = "WHERE c.name LIKE '$like' OR c.description LIKE '$like' OR c.category LIKE '$like'";
    }
    $res = $conn->query("
        SELECT c.channel_id, c.name, c.description, c.category,
               c.channel_image, c.created_on,
               COUNT(s.subscription_id) AS sub_count
        FROM   CHANNELS c
        LEFT JOIN SUBSCRIPTIONS s ON s.channel_id = c.channel_id
        $where
        GROUP BY c.channel_id
        ORDER BY sub_count DESC
        LIMIT 50
    ");
    while ($row = $res->fetch_assoc()) $channel_results[] = $row;
}

function formatDuration($secs) {
    $h = floor($secs / 3600);
    $m = floor(($secs % 3600) / 60);
    $s = $secs % 60;
    if ($h > 0) return sprintf('%d:%02d:%02d', $h, $m, $s);
    return sprintf('%d:%02d', $m, $s);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Search<?php if ($q !== '') echo ' — ' . htmlspecialchars($q); ?> — MiniTube</title>
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
    gap: 20px;
    position: sticky; top: 0; z-index: 100;
}
.nav-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; color: #fff; flex-shrink: 0; }
.nav-logo svg { width: 36px; height: 36px; }
.nav-logo span { font-size: 1.5rem; font-weight: 800; }
.nav-logo em { color: #ff008caf; font-style: normal; }
.nav-search {
    flex: 1;
    max-width: 560px;
    display: flex;
    gap: 0;
}
.nav-search input {
    flex: 1;
    padding: 9px 16px;
    border-radius: 8px 0 0 8px;
    border: 1px solid rgba(255,255,255,0.2);
    border-right: none;
    background: rgba(255,255,255,0.1);
    color: #fff;
    font-size: 0.95rem;
    outline: none;
}
.nav-search input::placeholder { color: #aaa; }
.nav-search input:focus { border-color: #ff008caf; background: rgba(255,255,255,0.15); }
.nav-search button {
    padding: 9px 18px;
    background: #ff008caf;
    color: #fff;
    border: none;
    border-radius: 0 8px 8px 0;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
}
.nav-search button:hover { background: #d4006a; }
.nav-links { display: flex; gap: 20px; flex-shrink: 0; }
.nav-links a { color: #ccc; text-decoration: none; font-size: 0.95rem; }
.nav-links a:hover { color: #ff008caf; }
.container { max-width: 960px; margin: 28px auto; padding: 0 20px; }
.filter-row {
    display: flex;
    gap: 10px;
    margin-bottom: 24px;
    flex-wrap: wrap;
    align-items: center;
}
 /* Layout of Page */
.filter-row span { font-size: 0.85rem; color: #aaa; }
.filter-btn {
    padding: 7px 18px;
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.2);
    background: rgba(255,255,255,0.07);
    color: #ccc;
    font-size: 0.88rem;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}
.filter-btn:hover, .filter-btn.active {
    background: #ff008caf;
    border-color: #ff008caf;
    color: #fff;
}
 /* Cards */
.card {
    background: rgba(0,0,0,0.6);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 12px;
    padding: 24px 28px;
    backdrop-filter: blur(8px);
    margin-bottom: 24px;
}
.card h2 {
    font-size: 0.85rem;
    font-weight: 700;
    color: #ff008caf;
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    text-transform: uppercase;
    letter-spacing: 1px;
}
/* Video rows */
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
    font-size: 0.98rem;
}
.video-title a:hover { color: #ff008caf; }
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
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 700px;
}
.video-meta {
    font-size: 0.8rem;
    color: #aaa;
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    margin-top: 4px;
}
.video-meta a { color: #cc66ff; text-decoration: none; }
.video-meta a:hover { color: #ff008caf; }
/* Channel rows */
.channel-row {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 12px 0;
    border-bottom: 1px solid rgba(255,255,255,0.07);
}
.channel-row:last-child { border-bottom: none; }
.channel-row img {
    width: 52px; height: 52px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #ff008caf;
    flex-shrink: 0;
}
.channel-info { flex: 1; }
.channel-info a {
    color: #fff;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.98rem;
}
.channel-info a:hover { color: #ff008caf; }
.channel-meta {
    font-size: 0.8rem;
    color: #aaa;
    display: flex;
    gap: 14px;
    margin-top: 4px;
    flex-wrap: wrap;
}
.channel-desc {
    font-size: 0.82rem;
    color: #888;
    margin-top: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 600px;
}
.empty {
    color: #666;
    font-size: 0.9rem;
    padding: 10px 0;
    text-align: center;
}
.query-info {
    font-size: 0.95rem;
    color: #bbb;
    margin-bottom: 20px;
}
.query-info strong { color: #fff; }
.no-query {
    text-align: center;
    padding: 60px 0;
    color: #666;
    font-size: 1rem;
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
        <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>"/>
        <input type="text" name="q"
               value="<?php echo htmlspecialchars($q); ?>"
               placeholder="Search videos and channels…"
               autofocus/>
        <button type="submit">Search</button>
    </form>

    <div class="nav-links">
        <a href="feed.php?user_id=<?php echo $user_id; ?>">Home</a>
        <a href="sql.html?user_id=<?php echo $user_id; ?>">SQL</a>
        <a href="login.html">Logout</a>
    </div>
</nav>

<div class="container">

<?php if ($q === ''): ?>
    <p class="query-info">
        Showing all <?php echo count($video_results) + count($channel_results); ?> results.
        Use the search bar above to filter.
    </p>
<?php else: ?>
    <p class="query-info">
        Results for <strong>"<?php echo htmlspecialchars($q); ?>"</strong>
        — <?php echo count($video_results) + count($channel_results); ?> found
    </p>
<?php endif; ?>

    <div class="filter-row">
        <span>Filter:</span>
        <a href="search.php?user_id=<?php echo $user_id; ?>&q=<?php echo urlencode($q); ?>&type=all"
           class="filter-btn <?php echo $type === 'all'      ? 'active' : ''; ?>">All</a>
        <a href="search.php?user_id=<?php echo $user_id; ?>&q=<?php echo urlencode($q); ?>&type=videos"
           class="filter-btn <?php echo $type === 'videos'   ? 'active' : ''; ?>">Videos</a>
        <a href="search.php?user_id=<?php echo $user_id; ?>&q=<?php echo urlencode($q); ?>&type=channels"
           class="filter-btn <?php echo $type === 'channels' ? 'active' : ''; ?>">Channels</a>
    </div>

    <?php if ($type === 'all' || $type === 'videos'): ?>
    <div class="card">
        <h2>Videos (<?php echo count($video_results); ?>)</h2>
        <?php if (empty($video_results)): ?>
            <p class="empty">No videos matched your search.</p>
        <?php else: ?>
            <?php foreach ($video_results as $v): ?>
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
                    <span>
                        <a href="channel.php?channel_id=<?php echo $v['channel_id']; ?>&user_id=<?php echo $user_id; ?>">
                            <?php echo htmlspecialchars($v['channel_name']); ?>
                        </a>
                    </span>
                    <span><?php echo formatDuration($v['duration_seconds']); ?></span>
                    <span><?php echo number_format($v['view_count']); ?> views</span>
                    <span><?php echo number_format($v['like_count']); ?> likes</span>
                    <span><?php echo date('d.m.Y', strtotime($v['uploaded_at'])); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($type === 'all' || $type === 'channels'): ?>
    <div class="card">
        <h2>Channels (<?php echo count($channel_results); ?>)</h2>
        <?php if (empty($channel_results)): ?>
            <p class="empty">No channels matched your search.</p>
        <?php else: ?>
            <?php foreach ($channel_results as $ch): ?>
            <div class="channel-row">
                <a href="channel.php?channel_id=<?php echo $ch['channel_id']; ?>&user_id=<?php echo $user_id; ?>">
                    <img src="<?php echo htmlspecialchars($ch['channel_image']); ?>"
                         alt="<?php echo htmlspecialchars($ch['name']); ?>"
                         onerror="this.src='https://picsum.photos/seed/default/200/200'"/>
                </a>
                <div class="channel-info">
                    <a href="channel.php?channel_id=<?php echo $ch['channel_id']; ?>&user_id=<?php echo $user_id; ?>">
                        <?php echo htmlspecialchars($ch['name']); ?>
                    </a>
                    <div class="channel-meta">
                        <span><?php echo htmlspecialchars($ch['category']); ?></span>
                        <span><?php echo number_format($ch['sub_count']); ?> subscribers</span>
                        <span>Since <?php echo date('Y', strtotime($ch['created_on'])); ?></span>
                    </div>
                    <?php if ($ch['description']): ?>
                    <div class="channel-desc"><?php echo htmlspecialchars($ch['description']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
<script>
window.addEventListener('pageshow', function(e) {
    if (e.persisted) window.location.reload();
});
</script>
</body>
</html>
<?php $conn->close(); ?>