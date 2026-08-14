<?php
//20220702045 Ece Duzgec

// Video watch page with player, like toggle, subscribe and threaded comments.

$servername = "localhost";
$dbuser     = "root";
$dbpass     = "mysql";
$dbname     = "ece_duzgec";

$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$user_id  = intval($_GET['user_id']  ?? 0);
$video_id = intval($_GET['video_id'] ?? 0);

if ($user_id <= 0 || $video_id <= 0) {
    header("Location: login.html");
    exit;
}

// Post comment or reply 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Like / unlike toggle, inserts or deletes a LIKES row and adjusts like_count.
    if (isset($_POST['action']) && $_POST['action'] === 'like') {
        $check = $conn->query("SELECT like_id FROM LIKES WHERE user_id = $user_id AND video_id = $video_id");
        if ($check->num_rows === 0) {
            // Not yet liked, add like
            $conn->query("INSERT INTO LIKES (user_id, video_id, liked_at) VALUES ($user_id, $video_id, NOW())");
            $conn->query("UPDATE VIDEOS SET like_count = like_count + 1 WHERE video_id = $video_id");
        } else {
            // Already liked, remove like
            $conn->query("DELETE FROM LIKES WHERE user_id = $user_id AND video_id = $video_id");
            $conn->query("UPDATE VIDEOS SET like_count = GREATEST(like_count - 1, 0) WHERE video_id = $video_id");
        }
        header("Location: watch.php?video_id=$video_id&user_id=$user_id&from_action=1");
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'subscribe') {
        $cid = intval($_POST['channel_id']);
        $stmt = $conn->prepare("INSERT IGNORE INTO SUBSCRIPTIONS (subscriber_id, channel_id, subscribed_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("ii", $user_id, $cid);
        $stmt->execute();
        $stmt->close();
        header("Location: watch.php?video_id=$video_id&user_id=$user_id&from_action=1");
        exit;
    }
    if (isset($_POST['action']) && $_POST['action'] === 'unsubscribe') {
        $cid = intval($_POST['channel_id']);
        $stmt = $conn->prepare("DELETE FROM SUBSCRIPTIONS WHERE subscriber_id = ? AND channel_id = ?");
        $stmt->bind_param("ii", $user_id, $cid);
        $stmt->execute();
        $stmt->close();
        header("Location: watch.php?video_id=$video_id&user_id=$user_id&from_action=1");
        exit;
    }

    $body      = trim($_POST['body'] ?? '');
    $parent_id = intval($_POST['parent_id'] ?? 0);

    if ($body !== '') {

        $pid = $parent_id > 0 ? $parent_id : null;

        $stmt = $conn->prepare("
            INSERT INTO COMMENTS (
                video_id,
                user_id,
                parent_comment_id,
                body,
                posted_at
            )
            VALUES (?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param("iiis", $video_id, $user_id, $pid, $body);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: watch.php?video_id=$video_id&user_id=$user_id&from_action=1");
    exit;
}

//  Increment views (only on fresh page load, not after actions) 
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET['from_action'])) {
    $conn->query("
        UPDATE VIDEOS
        SET view_count = view_count + 1
        WHERE video_id = $video_id
    ");
}

//  Get video info 
$v = $conn->query("
    SELECT
        v.*,
        c.name AS channel_name,
        c.channel_id,
        c.channel_image,
        u.full_name AS owner_name,
        u.country AS uploader_country,
        DATEDIFF(CURDATE(), v.uploaded_at) AS days_ago,
        CASE
            WHEN v.view_count >= 1000 THEN 'Popular'
            WHEN v.view_count >= 100  THEN 'Trending'
            ELSE 'New'
        END AS popularity_badge
    FROM VIDEOS v
    JOIN CHANNELS c ON c.channel_id = v.channel_id
    JOIN USERS u ON u.user_id = c.owner_id
    WHERE v.video_id = $video_id
")->fetch_assoc();

if (!$v) {
    die("Video not found.");
}

// YouTube ID extraction 
$yt_id = '';

$url = trim($v['url']);

if (preg_match(
    '~(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([^&?/]+)~',
    $url,
    $matches
)) {
    $yt_id = $matches[1];
}

// Comments 
$comments_res = $conn->query("
    SELECT
        c.comment_id,
        c.parent_comment_id,
        c.body,
        c.posted_at,
        u.full_name,
        u.user_image,
        u.user_id AS commenter_id
    FROM COMMENTS c
    JOIN USERS u ON u.user_id = c.user_id
    WHERE c.video_id = $video_id
    ORDER BY
        COALESCE(c.parent_comment_id, c.comment_id),
        c.parent_comment_id IS NOT NULL,
        c.posted_at ASC
");

//Current user 
$me = $conn->query("
    SELECT full_name, user_image
    FROM USERS
    WHERE user_id = $user_id
")->fetch_assoc();

//Subscription check 
$sub_res = $conn->query("SELECT 1 FROM SUBSCRIPTIONS WHERE subscriber_id = $user_id AND channel_id = {$v['channel_id']}");
$is_subscribed = $sub_res->num_rows > 0;

//Like check 
$like_res = $conn->query("SELECT like_id FROM LIKES WHERE user_id = $user_id AND video_id = $video_id");
$is_liked = $like_res->num_rows > 0;

//Duration formatter 
function fmtDur($s)
{
    $h   = floor($s / 3600);
    $m   = floor(($s % 3600) / 60);
    $sec = $s % 60;

    return $h > 0
        ? sprintf('%d:%02d:%02d', $h, $m, $sec)
        : sprintf('%d:%02d', $m, $sec);
}

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

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>
<?php
$short = mb_strlen($v['title']) > 40 ? mb_substr($v['title'], 0, 40) . '...' : $v['title'];
echo htmlspecialchars($short);
?> - MiniTube
</title>

<style>

*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}

body{
    font-family:'Segoe UI',sans-serif;
    background:url('watchspace.jpg') no-repeat center center fixed;
    background-size:cover;
    color:#fff;
    min-height:100vh;
}
 /* Navigation Panel */
nav{
    background:rgba(0,0,0,0.75);
    backdrop-filter:blur(10px);
    border-bottom:1px solid rgba(255,255,255,0.1);
    padding:14px 32px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:20px;
    position:sticky;
    top:0;
    z-index:100;
}

.nav-logo{
    display:flex;
    align-items:center;
    gap:10px;
    text-decoration:none;
    color:#fff;
}

.nav-logo svg{
    width:36px;
    height:36px;
}

.nav-logo span{
    font-size:1.5rem;
    font-weight:800;
}

.nav-logo em{
    color:#ff008caf;
    font-style:normal;
}

.nav-search{
    flex:1;
    max-width:480px;
    display:flex;
}

.nav-search input{
    flex:1;
    padding:8px 14px;
    border-radius:8px 0 0 8px;
    border:none;
    background:rgba(255,255,255,0.1);
    color:#fff;
    outline:none;
}

.nav-search button{
    padding:8px 16px;
    background:#ff008caf;
    color:#fff;
    border:none;
    border-radius:0 8px 8px 0;
    cursor:pointer;
}

.nav-links{
    display:flex;
    gap:20px;
}

.nav-links a{
    color:#ccc;
    text-decoration:none;
}
 /* Layout of Page */
.container{
    max-width:900px;
    margin:28px auto;
    padding:0 20px;
}
 /* Cards */
.card{
    background:rgba(0,0,0,0.65);
    border:1px solid rgba(255,255,255,0.12);
    border-radius:12px;
    padding:24px 28px;
    backdrop-filter:blur(8px);
    margin-bottom:24px;
}
 /* Video Rows */
.video-wrapper{
    position:relative;
    width:100%;
    padding-top:56.25%;
    background:#000;
    border-radius:10px;
    overflow:hidden;
    margin-bottom:20px;
}

.video-wrapper iframe{
    position:absolute;
    top:0;
    left:0;
    width:100%;
    height:100%;
    border:none;
}

#player-fallback{
    display:none;
    position:absolute;
    inset:0;
    background:#111;
    border-radius:10px;
    align-items:center;
    justify-content:center;
    flex-direction:column;
    gap:14px;
    color:#888;
}

.video-title{
    font-size:1.35rem;
    font-weight:800;
    margin-bottom:10px;
}
  /* Video Popularity Badge */
.pop-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    vertical-align: middle;
    margin-left: 10px;
}
.pop-badge.Popular  { background: #ff008caf; color: #fff; }
.pop-badge.Trending { background: rgb(0, 17, 255); color: #fff; }
.pop-badge.New      { background: rgba(238, 0, 0, 0.91); color: #ccc; }

.video-meta{
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    gap:16px;
    font-size:0.85rem;
    color:#aaa;
    margin-bottom:14px;
}

.video-desc{
    font-size:0.9rem;
    color:#ccc;
    line-height:1.6;
    border-top:1px solid rgba(255,255,255,0.08);
    padding-top:14px;
    margin-top:4px;
}
  /* Buttons */
.like-btn {
    padding: 7px 20px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.18);
    border-radius: 6px;
    color: #fff;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s;
}
.like-btn:hover {
    background: rgba(255,0,140,0.2);
    border-color: #ff008caf;
    color: #ff008caf;
}
.like-btn--active {
    background: rgba(255,0,140,0.25);
    border-color: #ff008caf;
    color: #ff008caf;
}

.channel-strip{
    display:flex;
    align-items:center;
    gap:14px;
    padding:14px 0;
    border-top:1px solid rgba(255,255,255,0.08);
    margin-top:14px;
}

.channel-strip img{
    width:48px;
    height:48px;
    border-radius:50%;
    object-fit:cover;
}

.channel-strip a{
    color:#fff;
    text-decoration:none;
    font-weight:700;
}

.sub-btn {
    padding: 8px 20px;
    background: #ff008caf;
    border: none;
    border-radius: 6px;
    color: #fff;
    font-size: 0.88rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s;
}
.sub-btn:hover { background: #d4006a; }
.sub-btn--active {
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.25);
    color: #ccc;
}
.sub-btn--active:hover { background: rgba(255,0,0,0.25); color: #fff; }

.comment{
    display:flex;
    gap:12px;
    padding:14px 0;
    border-bottom:1px solid rgba(255,255,255,0.07);
}

.comment img{
    width:38px;
    height:38px;
    border-radius:50%;
    object-fit:cover;
}

.comment-body{
    flex:1;
}

.comment-author{
    font-size:0.85rem;
    font-weight:700;
}

.comment-author span{
    color:#777;
    margin-left:8px;
    font-size:0.75rem;
}

.comment-text{
    margin-top:5px;
    color:#ddd;
    line-height:1.5;
}

/* Comments header */
.comments-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 22px;
    padding-bottom: 14px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.comments-header h2 {
    font-size: 1rem;
    font-weight: 700;
    color: #ff008caf;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Write comment area */
.comment-compose {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    margin-bottom: 28px;
    padding-bottom: 24px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
.comment-compose .me-avatar {
    width: 40px; height: 40px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    border: 2px solid #ff008caf;
}
.comment-compose-inner { flex: 1; }
.comment-compose-inner .me-name {
    font-size: 0.82rem;
    font-weight: 700;
    color: #ff008caf;
    margin-bottom: 6px;
}
.comment-compose textarea {
    width: 100%;
    min-height: 80px;
    padding: 12px 14px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.15);
    background: rgba(255,255,255,0.07);
    color: #fff;
    font-family: inherit;
    font-size: 0.92rem;
    resize: vertical;
    outline: none;
    transition: border-color 0.2s;
}
.comment-compose textarea:focus { border-color: #ff008caf; background: rgba(255,255,255,0.1); }
.comment-compose textarea::placeholder { color: #555; }
.compose-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 8px;
}
.compose-actions button {
    padding: 9px 24px;
    background: #ff008caf;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s;
}
.compose-actions button:hover { background: #d4006a; }

/* Comment thread */
.comment {
    display: flex;
    gap: 12px;
    padding: 14px 0 6px;
}
.comment > img {
    width: 38px; height: 38px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    border: 1px solid rgba(255,255,255,0.1);
}
.comment.is-mine > img { border-color: #ff008caf; }
.comment-body { flex: 1; }
.comment-author {
    font-size: 0.85rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.comment-author span {
    color: #666;
    font-size: 0.75rem;
    font-weight: 400;
}
.comment-text {
    margin-top: 5px;
    color: #ddd;
    line-height: 1.55;
    font-size: 0.92rem;
}
.comment.is-mine .comment-text { color: #eee; }

/* Replies */
.replies { margin-top: 6px; padding-left: 4px; }
.reply {
    display: flex;
    gap: 10px;
    padding: 10px 0 4px 14px;
    border-left: 2px solid rgba(255,0,140,0.25);
    margin-top: 8px;
}
.reply img {
    width: 30px; height: 30px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    border: 1px solid rgba(255,255,255,0.1);
}
.reply.is-mine img { border-color: #ff008caf; }
.reply .comment-author { font-size: 0.82rem; }
.reply .comment-text   { font-size: 0.85rem; color: #bbb; }
.reply.is-mine .comment-text { color: #eee; }

.reply-btn {
    background: none;
    border: none;
    color: #888;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    padding: 5px 0 2px;
    margin-top: 4px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: color 0.2s;
}
.reply-btn:hover { color: #ff008caf; }

/* Inline reply form */
.reply-form {
    display: none;
    margin-top: 10px;
    padding-left: 14px;
    border-left: 2px solid rgba(255,0,140,0.3);
}
.reply-form.open { display: flex; gap: 10px; align-items: flex-start; }
.reply-form .me-avatar-sm {
    width: 28px; height: 28px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    border: 2px solid #ff008caf;
    margin-top: 2px;
}
.reply-form-inner { flex: 1; }
.reply-form textarea {
    width: 100%;
    padding: 9px 12px;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 8px;
    color: #fff;
    font-size: 0.85rem;
    font-family: inherit;
    resize: vertical;
    min-height: 58px;
    outline: none;
    transition: border-color 0.2s;
}
.reply-form textarea:focus { border-color: #ff008caf; }
.reply-form textarea::placeholder { color: #555; }
.reply-form-actions {
    display: flex;
    gap: 8px;
    margin-top: 8px;
    justify-content: flex-end;
}
.reply-form-actions button[type=submit] {
    padding: 6px 16px;
    background: #ff008caf;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
}
.reply-form-actions button[type=submit]:hover { background: #d4006a; }
.reply-form-actions button[type=button] {
    padding: 6px 12px;
    background: rgba(255,255,255,0.07);
    color: #aaa;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 6px;
    font-size: 0.82rem;
    cursor: pointer;
}
.reply-form-actions button[type=button]:hover { background: rgba(255,255,255,0.13); color: #fff; }

.no-comments { color: #666; font-size: 0.9rem; padding: 20px 0; text-align: center; }
.thread-divider { border: none; border-top: 1px solid rgba(255,255,255,0.06); margin: 2px 0; }

</style>

</head>

<body>

<nav>

    <a class="nav-logo"
       href="feed.php?user_id=<?php echo $user_id; ?>">

        <svg viewBox="0 0 90 90">
            <rect width="90" height="90" rx="20" fill="#ff008caf"/>
            <polygon points="35,25 65,45 35,65" fill="#fff"/>
        </svg>

        <span>Mini<em>Tube</em></span>

    </a>

    <form class="nav-search"
          action="search.php"
          method="GET">

        <input type="hidden"
               name="user_id"
               value="<?php echo $user_id; ?>">

        <input type="text"
               name="q"
               placeholder="Search videos...">

        <button type="submit">Search</button>

    </form>

    <div class="nav-links">
        <a href="feed.php?user_id=<?php echo $user_id; ?>">Home</a>
        <a href="login.html">Logout</a>
    </div>

</nav>

<div class="container">

<div class="card">

<?php if($yt_id !== ''): ?>

<div class="video-wrapper">

    <iframe
        id="yt-frame"
        src="https://www.youtube.com/embed/<?php echo htmlspecialchars($yt_id); ?>?rel=0"
        title="<?php echo htmlspecialchars($v['title']); ?>"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
        allowfullscreen>
    </iframe>

    <div id="player-fallback">

        <svg width="48"
             height="48"
             viewBox="0 0 24 24"
             fill="none"
             stroke="#555"
             stroke-width="1.5">

            <circle cx="12" cy="12" r="10"/>
            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>

        </svg>

        <span>This video cannot be embedded.</span>

        <a href="<?php echo htmlspecialchars($v['url']); ?>"
           target="_blank"
           style="
               padding:8px 20px;
               background:#ff008caf;
               color:#fff;
               border-radius:6px;
               text-decoration:none;
               font-size:0.88rem;
               font-weight:700;
           ">
            Watch on YouTube ↗
        </a>

    </div>

</div>

<script>

(function () {

    const frame = document.getElementById('yt-frame');
    const fallback = document.getElementById('player-fallback');

    frame.addEventListener('error', function () {

        frame.style.display = 'none';
        fallback.style.display = 'flex';

    });

})();

</script>

<?php else: ?>

<div style="
    background:#111;
    border-radius:10px;
    padding:40px;
    text-align:center;
    color:#555;
    margin-bottom:20px;
">
    No video URL stored for this entry.
</div>

<?php endif; ?>

<div class="video-title">
    <?php echo htmlspecialchars($v['title']); ?>
    <span class="pop-badge <?php echo htmlspecialchars($v['popularity_badge']); ?>">
        <?php echo htmlspecialchars($v['popularity_badge']); ?>
    </span>
</div>

<div class="video-meta">

    <span>
        <?php echo number_format($v['view_count']); ?> views
    </span>

    <span>
        <?php echo number_format($v['like_count']); ?> likes
    </span>

    <span>
        <?php echo fmtDur($v['duration_seconds']); ?>
    </span>

    <span>
        <?php echo htmlspecialchars($v['uploader_country']); ?>
    </span>

    <span>
        Uploaded <?php echo date('d.m.Y', strtotime($v['uploaded_at'])); ?> · <?php echo timeAgo($v['days_ago']); ?>
    </span>

    <form method="POST" action="watch.php?video_id=<?php echo $video_id; ?>&user_id=<?php echo $user_id; ?>">
        <input type="hidden" name="action" value="like">
        <button class="like-btn<?php echo $is_liked ? ' like-btn--active' : ''; ?>" type="submit">
            <?php echo $is_liked ? 'Unlike' : 'Like'; ?>
        </button>
    </form>

</div>

<?php if ($v['description']): ?>

<div class="video-desc">
    <?php echo nl2br(htmlspecialchars($v['description'])); ?>
</div>

<?php endif; ?>

<div class="channel-strip">

    <img src="<?php echo htmlspecialchars($v['channel_image']); ?>">

    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">

        <div>
            <a href="channel.php?channel_id=<?php echo $v['channel_id']; ?>&user_id=<?php echo $user_id; ?>">
                <?php echo htmlspecialchars($v['channel_name']); ?>
            </a>
            <div style="font-size:0.8rem;color:#888;margin-top:2px;">
                <?php echo htmlspecialchars($v['owner_name']); ?>
            </div>
        </div>

        <form method="POST" action="watch.php?video_id=<?php echo $video_id; ?>&user_id=<?php echo $user_id; ?>">
            <input type="hidden" name="channel_id" value="<?php echo $v['channel_id']; ?>">
            <input type="hidden" name="action" value="<?php echo $is_subscribed ? 'unsubscribe' : 'subscribe'; ?>">
            <button type="submit" class="sub-btn <?php echo $is_subscribed ? 'sub-btn--active' : ''; ?>">
                <?php echo $is_subscribed ? 'Unsubscribe' : 'Subscribe'; ?>
            </button>
        </form>

    </div>

</div>

</div>

<div class="card">

<?php
// Build the threaded structure from the SQL query results
$threads = [];   
$order   = [];   

while ($row = $comments_res->fetch_assoc()) {
    if ($row['parent_comment_id'] === null) {
        $id = $row['comment_id'];
        $threads[$id] = ['comment' => $row, 'replies' => []];
        $order[] = $id;
    } else {
        $pid = $row['parent_comment_id'];
        if (isset($threads[$pid])) {
            $threads[$pid]['replies'][] = $row;
        }
    }
}

$total_comments = 0;
foreach ($threads as $t) {
    $total_comments += 1 + count($t['replies']);
}
?>

<div class="comments-header">
    <h2>Comments (<?php echo $total_comments; ?>)</h2>
</div>

<!-- Threads -->
<?php if (empty($threads)): ?>
    <p class="no-comments">No comments yet — be the first!</p>
<?php else: ?>
    <?php foreach (array_reverse($order) as $tid): ?>
        <?php $t = $threads[$tid]; $c = $t['comment']; $isMine = ($c['commenter_id'] == $user_id); ?>
        <hr class="thread-divider">
        <div class="comment<?php echo $isMine ? ' is-mine' : ''; ?>">

            <img src="<?php echo htmlspecialchars($c['user_image']); ?>"
                 onerror="this.src='https://i.pravatar.cc/150?img=1'"
                 alt="<?php echo htmlspecialchars($c['full_name']); ?>">

            <div class="comment-body">
                <div class="comment-author">
                    <?php echo htmlspecialchars($c['full_name']); ?>
                    <span><?php echo date('d.m.Y', strtotime($c['posted_at'])); ?></span>
                </div>
                <div class="comment-text"><?php echo nl2br(htmlspecialchars($c['body'])); ?></div>

                <button class="reply-btn" type="button" onclick="toggleReply('rf-<?php echo $tid; ?>')">
                    Reply<?php if (!empty($t['replies'])): ?> · <?php echo count($t['replies']); ?><?php endif; ?>
                </button>

                <!-- Inline reply form with user avatar -->
                <div class="reply-form" id="rf-<?php echo $tid; ?>">
                    <img class="me-avatar-sm"
                         src="<?php echo htmlspecialchars($me['user_image']); ?>"
                         onerror="this.src='https://i.pravatar.cc/150?img=1'">
                    <div class="reply-form-inner">
                        <form method="POST" action="watch.php?video_id=<?php echo $video_id; ?>&user_id=<?php echo $user_id; ?>">
                            <input type="hidden" name="parent_id" value="<?php echo $tid; ?>">
                            <textarea name="body"
                                      placeholder="Replying as <?php echo htmlspecialchars($me['full_name']); ?>…"
                                      required></textarea>
                            <div class="reply-form-actions">
                                <button type="button" onclick="toggleReply('rf-<?php echo $tid; ?>')">Cancel</button>
                                <button type="submit">Post Reply</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Existing replies -->
                <?php if (!empty($t['replies'])): ?>
                <div class="replies">
                    <?php foreach ($t['replies'] as $r): $rIsMine = ($r['commenter_id'] == $user_id); ?>
                    <div class="reply<?php echo $rIsMine ? ' is-mine' : ''; ?>">
                        <img src="<?php echo htmlspecialchars($r['user_image']); ?>"
                             onerror="this.src='https://i.pravatar.cc/150?img=1'"
                             alt="<?php echo htmlspecialchars($r['full_name']); ?>">
                        <div class="comment-body">
                            <div class="comment-author">
                                <?php echo htmlspecialchars($r['full_name']); ?>
                                <span><?php echo date('d.m.Y', strtotime($r['posted_at'])); ?></span>
                            </div>
                            <div class="comment-text"><?php echo nl2br(htmlspecialchars($r['body'])); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<hr class="thread-divider" style="margin-top:16px;">

<!-- Compose box at bottom -->
<div class="comment-compose" style="margin-top:20px; margin-bottom:0; padding-bottom:0; border-bottom:none;">
    <img class="me-avatar"
         src="<?php echo htmlspecialchars($me['user_image']); ?>"
         alt="<?php echo htmlspecialchars($me['full_name']); ?>"
         onerror="this.src='https://i.pravatar.cc/150?img=1'">
    <div class="comment-compose-inner">
        <div class="me-name"><?php echo htmlspecialchars($me['full_name']); ?></div>
        <form method="POST" action="watch.php?video_id=<?php echo $video_id; ?>&user_id=<?php echo $user_id; ?>">
            <textarea name="body" placeholder="Share your thoughts…" required></textarea>
            <div class="compose-actions">
                <button type="submit">Post Comment</button>
            </div>
        </form>
    </div>
</div>

</div>

</div>

<script>
function toggleReply(id) {
    const form = document.getElementById(id);
    const isOpen = form.classList.contains('open');
    document.querySelectorAll('.reply-form.open').forEach(f => f.classList.remove('open'));
    if (!isOpen) {
        form.classList.add('open');
        form.querySelector('textarea').focus();
    }
}
</script>

</body>
</html>

<?php $conn->close(); ?>