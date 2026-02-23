<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// Create messages table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `sender_id` INT NOT NULL,
        `receiver_id` INT NOT NULL,
        `conversation_id` VARCHAR(100),
        `message_text` TEXT NOT NULL,
        `is_read` TINYINT DEFAULT 0,
        `created_at` DATETIME,
        KEY `sender_id` (`sender_id`),
        KEY `receiver_id` (`receiver_id`),
        KEY `conversation_id` (`conversation_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

$user_id = $_SESSION['user']['id'];
$action = $_GET['action'] ?? 'conversations';
$other_user_id = (int)($_GET['user'] ?? 0);

// Mark messages as read
if ($other_user_id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send_message') {
        $message = trim($_POST['message'] ?? '');
        if ($message) {
            $conv_id = min($user_id, $other_user_id) . '_' . max($user_id, $other_user_id);
            $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, conversation_id, message_text, created_at) VALUES (?, ?, ?, ?, NOW())');
            $stmt->execute([$user_id, $other_user_id, $conv_id, $message]);
        }
    }
}

// Get conversations
$conversations = [];
try {
    // Messages dikirim ke user
    $stmt = $pdo->prepare("SELECT DISTINCT 
        CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as other_user_id,
        CASE WHEN sender_id = ? THEN (SELECT username FROM users WHERE id = receiver_id) ELSE (SELECT username FROM users WHERE id = sender_id) END as username,
        MAX(created_at) as last_message_time,
        (SELECT message_text FROM messages m2 WHERE 
            ((m2.sender_id = ? AND m2.receiver_id = other_user_id) OR 
             (m2.sender_id = other_user_id AND m2.receiver_id = ?))
         ORDER BY m2.created_at DESC LIMIT 1) as last_message,
        (SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND sender_id = other_user_id AND is_read = 0) as unread_count
    FROM messages 
    WHERE sender_id = ? OR receiver_id = ?
    GROUP BY other_user_id
    ORDER BY last_message_time DESC");
    
    $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
    $conversations = $stmt->fetchAll();
} catch (Exception $e) {}

// Get messages dengan specific user
$messages = [];
$other_user = null;
if ($other_user_id) {
    try {
        // Get other user info
        $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
        $stmt->execute([$other_user_id]);
        $other_user = $stmt->fetch();
        
        // Get messages
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE 
            (sender_id = ? AND receiver_id = ?) OR 
            (sender_id = ? AND receiver_id = ?)
         ORDER BY created_at ASC");
        $stmt->execute([$user_id, $other_user_id, $other_user_id, $user_id]);
        $messages = $stmt->fetchAll();
        
        // Mark as read
        $stmt = $pdo->prepare('UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ?');
        $stmt->execute([$user_id, $other_user_id]);
    } catch (Exception $e) {}
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $other_user_id ? 'Chat dengan ' . htmlspecialchars($other_user['username'] ?? '') : 'Chat' ?> - BirdKita</title>
  <link rel="stylesheet" href="style.css">
  <style>
    /* WhatsApp Style Chat */
    .chat-view {
      display: grid;
      grid-template-columns: 320px 1fr;
      height: 700px;
      gap: 16px;
      background: #f0f2f5;
    }
    
    @media (max-width: 768px) {
      .chat-view {
        grid-template-columns: 1fr;
        height: auto;
      }
    }
    
    /* Sidebar - Conversations List */
    .chat-sidebar {
      background: #ffffff;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      display: flex;
      flex-direction: column;
    }
    
    .chat-sidebar-header {
      background: #128c7e;
      color: white;
      padding: 16px;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 12px;
      position: sticky;
      top: 0;
      z-index: 10;
    }
    
    .chat-sidebar-header .status-dot {
      width: 8px;
      height: 8px;
      background: #4caf50;
      border-radius: 50%;
      box-shadow: 0 0 0 2px rgba(255,255,255,0.3);
    }
    
    .conversations-list {
      flex: 1;
      overflow-y: auto;
    }
    
    .conversation-item {
      padding: 12px 16px;
      border-bottom: 1px solid #f0f0f0;
      cursor: pointer;
      transition: background 0.2s;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .conversation-item:hover {
      background: #f5f5f5;
    }
    
    .conversation-item.active {
      background: #d9fdd3;
      border-left: 4px solid #128c7e;
    }
    
    .conversation-avatar {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: #ddd;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      color: #666;
      font-size: 18px;
    }
    
    .conversation-info {
      flex: 1;
      min-width: 0;
    }
    
    .conversation-name {
      font-weight: 600;
      color: #111;
      margin-bottom: 4px;
    }
    
    .conversation-preview {
      font-size: 13px;
      color: #666;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    
    .conversation-time {
      font-size: 11px;
      color: #999;
      margin-left: auto;
      white-space: nowrap;
    }
    
    .conversation-unread {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: #128c7e;
      color: white;
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 700;
      min-width: 20px;
      height: 20px;
    }
    
    /* Chat Panel */
    .chat-panel {
      background: #ffffff;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      display: flex;
      flex-direction: column;
      position: relative;
    }
    
    .chat-panel-header {
      background: #128c7e;
      color: white;
      padding: 16px;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 12px;
      position: sticky;
      top: 0;
      z-index: 10;
    }
    
    .chat-panel-header .status-dot {
      width: 8px;
      height: 8px;
      background: #4caf50;
      border-radius: 50%;
      box-shadow: 0 0 0 2px rgba(255,255,255,0.3);
    }
    
    .chat-panel-header .status-text {
      font-size: 12px;
      opacity: 0.9;
      margin-left: auto;
    }
    
    /* Messages Area */
    .chat-messages {
      flex: 1;
      overflow-y: auto;
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 12px;
      background: #e5ddd5;
      background-image: 
        radial-gradient(circle at 25px 25px, #d1c4e9 2px, transparent 0),
        radial-gradient(circle at 75px 75px, #d1c4e9 2px, transparent 0);
      background-size: 100px 100px;
      background-position: 0 0, 50px 50px;
    }
    
    .message-date {
      text-align: center;
      font-size: 11px;
      color: #666;
      margin: 16px 0;
      background: rgba(0,0,0,0.1);
      padding: 4px 8px;
      border-radius: 12px;
      display: inline-block;
      margin-left: auto;
      margin-right: auto;
    }
    
    .chat-message {
      display: flex;
      gap: 8px;
      align-items: flex-end;
      max-width: 80%;
    }
    
    .chat-message.sent {
      justify-content: flex-end;
      margin-left: auto;
    }
    
    .chat-message.received .message-avatar {
      display: none;
    }
    
    .message-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: #ddd;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      color: #666;
      font-size: 14px;
      flex-shrink: 0;
    }
    
    .message-content {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    
    .chat-message-bubble {
      padding: 10px 14px;
      border-radius: 18px;
      word-wrap: break-word;
      font-size: 14px;
      line-height: 1.4;
      position: relative;
      max-width: 100%;
    }
    
    .chat-message.received .chat-message-bubble {
      background: #ffffff;
      color: #111;
      border: 1px solid #e0e0e0;
      border-radius: 18px 18px 18px 4px;
    }
    
    .chat-message.sent .chat-message-bubble {
      background: #075e54;
      color: white;
      border-radius: 18px 18px 4px 18px;
    }
    
    .chat-message-time {
      font-size: 11px;
      color: #999;
      text-align: right;
      margin-top: 2px;
    }
    
    .chat-message.received .chat-message-time {
      color: #999;
      text-align: left;
    }
    
    /* Input Area */
    .chat-input-area {
      padding: 16px;
      border-top: 1px solid #e0e0e0;
      background: #f0f2f5;
      display: flex;
      gap: 8px;
      align-items: flex-end;
    }
    
    .chat-input-area .input-container {
      flex: 1;
      display: flex;
      align-items: flex-end;
      gap: 8px;
      background: white;
      border-radius: 24px;
      padding: 8px 12px;
      border: 1px solid #e0e0e0;
    }
    
    .chat-input-area textarea {
      flex: 1;
      border: none;
      outline: none;
      resize: none;
      font-family: inherit;
      font-size: 14px;
      line-height: 1.4;
      min-height: 40px;
      max-height: 120px;
      padding: 4px 8px;
    }
    
    .chat-input-area .input-actions {
      display: flex;
      gap: 8px;
    }
    
    .chat-input-area button {
      padding: 8px 12px;
      background: #128c7e;
      color: white;
      border: none;
      border-radius: 50%;
      cursor: pointer;
      font-weight: 600;
      transition: background 0.2s;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .chat-input-area button:hover {
      background: #075e54;
    }
    
    .chat-input-area button.secondary {
      background: #ddd;
      color: #666;
    }
    
    .chat-input-area button.secondary:hover {
      background: #ccc;
    }
    
    /* Empty State */
    .empty-chat {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100%;
      color: #999;
      text-align: center;
      background: #e5ddd5;
      background-image: 
        radial-gradient(circle at 25px 25px, #d1c4e9 2px, transparent 0),
        radial-gradient(circle at 75px 75px, #d1c4e9 2px, transparent 0);
      background-size: 100px 100px;
      background-position: 0 0, 50px 50px;
    }
    
    .empty-chat .icon {
      font-size: 64px;
      margin-bottom: 16px;
      opacity: 0.5;
    }
    
    /* Scrollbar styling */
    .conversations-list::-webkit-scrollbar,
    .chat-messages::-webkit-scrollbar {
      width: 6px;
    }
    
    .conversations-list::-webkit-scrollbar-track,
    .chat-messages::-webkit-scrollbar-track {
      background: #f1f1f1;
    }
    
    .conversations-list::-webkit-scrollbar-thumb,
    .chat-messages::-webkit-scrollbar-thumb {
      background: #c1c1c1;
      border-radius: 3px;
    }
    
    .conversations-list::-webkit-scrollbar-thumb:hover,
    .chat-messages::-webkit-scrollbar-thumb:hover {
      background: #a8a8a8;
    }
  </style>
</head>
<body>
  <div class="site-wrap">
    <header class="site-header">
      <div class="nav-inner">
        <div class="brand">
          <img src="assets/logo.svg" alt="Logo" class="logo">
          <span class="brand-title">BirdKita</span>
        </div>
        <div style="flex:1"></div>
        <div class="user-actions">
          <a href="logout.php" class="logout">Logout</a>
        </div>
      </div>
    </header>


    <main class="main">

      <div class="user-actions">
          <a href="dashboard.php" class="btn" style="background:var(--accent);color:var(--text-dark)">← Kembali</a>
        </div>
        
      <h1 style="color:var(--primary);margin-bottom:20px">💬 Pesan & Chat</h1>
      
      <div class="chat-view">
        <!-- Sidebar - List Conversations -->
        <div class="chat-sidebar">
          <div class="chat-sidebar-header">Percakapan (<?= count($conversations) ?>)</div>
          
          <div class="conversations-list">
            <?php if (!$conversations): ?>
              <div style="padding:16px;text-align:center;color:#999">
                <p style="margin:0">Belum ada percakapan</p>
              </div>
            <?php else: ?>
              <?php foreach ($conversations as $conv): ?>
                <a href="messages.php?user=<?= $conv['other_user_id'] ?>" style="text-decoration:none">
                  <div class="conversation-item <?= $other_user_id === (int)$conv['other_user_id'] ? 'active' : '' ?>">
                    <div class="conversation-name">
                      <?= htmlspecialchars($conv['username']) ?>
                      <?php if ($conv['unread_count'] > 0): ?>
                        <span class="conversation-unread"><?= $conv['unread_count'] ?></span>
                      <?php endif; ?>
                    </div>
                    <div class="conversation-preview"><?= htmlspecialchars(substr($conv['last_message'] ?? '', 0, 40)) ?></div>
                  </div>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Chat Panel -->
        <div class="chat-panel">
          <?php if ($other_user): ?>
            <div class="chat-panel-header">
              <div class="conversation-avatar">
                <?= strtoupper(substr(htmlspecialchars($other_user['username']), 0, 1)) ?>
              </div>
              <div>
                <?= htmlspecialchars($other_user['username']) ?>
                <div class="status-text">online</div>
              </div>
            </div>
            
            <div class="chat-messages">
              <?php if (!$messages): ?>
                <div class="message-date">Hari ini</div>
                <div class="empty-chat">
                  <div>
                    <div class="icon">💬</div>
                    <p style="margin:0;color:#666">Mulai percakapan dengan <?= htmlspecialchars($other_user['username']) ?></p>
                  </div>
                </div>
              <?php else: ?>
                <div class="message-date">Hari ini</div>
                <?php foreach ($messages as $msg): ?>
                  <div class="chat-message <?= $msg['sender_id'] === $user_id ? 'sent' : 'received' ?>">
                    <?php if ($msg['sender_id'] !== $user_id): ?>
                      <div class="message-avatar">
                        <?= strtoupper(substr(htmlspecialchars($other_user['username']), 0, 1)) ?>
                      </div>
                    <?php endif; ?>
                    <div class="message-content">
                      <div class="chat-message-bubble"><?= htmlspecialchars($msg['message_text']) ?></div>
                      <div class="chat-message-time"><?= htmlspecialchars(date('H:i', strtotime($msg['created_at']))) ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            
            <form method="post" class="chat-input-area">
              <input type="hidden" name="action" value="send_message">
              <div class="input-container">
                <button type="button" class="secondary" title="Emoji">😊</button>
                <textarea name="message" placeholder="Ketik pesan Anda..." required></textarea>
                <button type="button" class="secondary" title="Attachment">📎</button>
              </div>
              <button type="submit" title="Kirim">➤</button>
            </form>
          <?php else: ?>
            <div class="empty-chat">
              <div>
                <p style="font-size:24px;margin:0">💬</p>
                <p>Pilih percakapan untuk mulai chat</p>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </main>

    <footer class="site-footer">
      <div class="footer-inner">🐦 BirdKita - Marketplace & Komunitas Burung Indonesia © 2026</div>
    </footer>
  </div>

  <script>
    // Auto scroll ke bottom
    const chatMessages = document.querySelector('.chat-messages');
    if (chatMessages) {
      chatMessages.scrollTop = chatMessages.scrollHeight;
    }
  </script>
</body>
</html>
