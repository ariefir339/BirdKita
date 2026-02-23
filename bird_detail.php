<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$bird_id = (int)($_GET['id'] ?? 0);
if (!$bird_id) {
    header('Location: dashboard.php');
    exit;
}

// Get bird details
try {
    $stmt = $pdo->prepare('SELECT w.*, u.username as seller_name FROM winners w LEFT JOIN users u ON w.uploaded_by = u.id WHERE w.id = ?');
    $stmt->execute([$bird_id]);
    $bird = $stmt->fetch();
    
    if (!$bird) {
        header('Location: dashboard.php');
        exit;
    }
} catch (Exception $e) {
    $bird = null;
}

// Get reviews
$reviews = [];
try {
    $stmt = $pdo->prepare('SELECT r.*, u.username FROM reviews r JOIN users u ON r.reviewer_id = u.id WHERE r.bird_id = ? ORDER BY r.created_at DESC');
    $stmt->execute([$bird_id]);
    $reviews = $stmt->fetchAll();
} catch (Exception $e) {}

// Processing
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Add to cart / Create order
    if ($action === 'buy_bird') {
        $qty = (int)($_POST['qty'] ?? 1);
        if ($qty > 0 && $bird) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT,
                    `bird_id` INT,
                    `bird_name` VARCHAR(255),
                    `bird_type` VARCHAR(255),
                    `bird_price` VARCHAR(100),
                    `quantity` INT,
                    `total_price` VARCHAR(100),
                    `status` VARCHAR(50) DEFAULT 'Pending',
                    `created_at` DATETIME
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (Exception $e) {}
            
            $total = floatval($bird['bird_price'] ?? 0) * $qty;
            $stmt = $pdo->prepare('INSERT INTO orders (user_id, bird_id, bird_name, bird_type, bird_price, quantity, total_price, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$_SESSION['user']['id'], $bird_id, $bird['bird_name'], $bird['bird_type'], $bird['bird_price'], $qty, $total]);
            
            $message = '✓ Pesanan sukses dibuat! Admin akan mensong dalam 24 jam.';
        }
    }
    
    // Add review
    if ($action === 'add_review') {
        $rating = (int)($_POST['rating'] ?? 5);
        $comment = trim($_POST['comment'] ?? '');
        
        if ($comment && $rating >= 1 && $rating <= 5) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `bird_id` INT,
                    `reviewer_id` INT,
                    `rating` INT,
                    `comment` TEXT,
                    `created_at` DATETIME
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (Exception $e) {}
            
            $stmt = $pdo->prepare('INSERT INTO reviews (bird_id, reviewer_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())');
            $stmt->execute([$bird_id, $_SESSION['user']['id'], $rating, $comment]);
            
            $message = '✓ Ulasan ditambahkan!';
            
            // Refresh reviews
            $stmt = $pdo->prepare('SELECT r.*, u.username FROM reviews r JOIN users u ON r.reviewer_id = u.id WHERE r.bird_id = ? ORDER BY r.created_at DESC');
            $stmt->execute([$bird_id]);
            $reviews = $stmt->fetchAll();
        }
    }
}

// Get seller contact info
$seller_contact = '';
if ($bird['uploaded_by']) {
    try {
        $stmt = $pdo->prepare('SELECT seller_phone, seller_address FROM user_profiles WHERE user_id = ?');
        $stmt->execute([$bird['uploaded_by']]);
        $profile = $stmt->fetch();
        if ($profile) {
            $seller_contact = $profile['seller_phone'] . ' | ' . $profile['seller_address'];
        }
    } catch (Exception $e) {}
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($bird['bird_name'] ?? 'Detail Burung') ?> - BirdKita</title>
  <link rel="stylesheet" href="style.css">
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
          <a href="dashboard.php" class="btn" style="background:var(--accent);color:var(--text-dark)">← Kembali</a>
          <a href="logout.php" class="logout">Logout</a>
        </div>
      </div>
    </header>

    <main class="main">
      <?php if ($message): ?>
        <div class="messages" style="margin-bottom:20px">
          <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>

      <?php if ($bird): ?>
        <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">
          <!-- Foto -->
          <div style="background:white;padding:16px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.08)">
            <?php if (!empty($bird['image_path']) && file_exists($bird['image_path'])): ?>
              <img src="<?= htmlspecialchars($bird['image_path']) ?>" alt="<?= htmlspecialchars($bird['bird_name']) ?>" style="width:100%;border-radius:8px;display:block">
            <?php else: ?>
              <img src="assets/parrot.svg" alt="<?= htmlspecialchars($bird['bird_name']) ?>" style="width:100%;border-radius:8px;display:block">
            <?php endif; ?>
          </div>

          <!-- Info & Beli -->
          <div style="background:white;padding:20px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.08)">
            <h1 style="margin:0 0 12px 0;color:var(--primary)"><?= htmlspecialchars($bird['bird_name']) ?></h1>
            
            <div style="font-size:14px;color:#666;margin-bottom:16px">
              <p><strong>📌 Jenis:</strong> <?= htmlspecialchars($bird['bird_type'] ?? '-') ?></p>
              <p><strong>🏆 Penghargaan:</strong> <?= htmlspecialchars($bird['bird_rank'] ?? 'Tidak ada') ?></p>
              <p><strong>📅 Diupload:</strong> <?= htmlspecialchars($bird['created_at'] ?? '-') ?></p>
            </div>

            <div style="background:var(--accent);padding:12px;border-radius:8px;margin-bottom:16px;text-align:center">
              <div style="font-size:12px;color:var(--text-dark);text-transform:uppercase;letter-spacing:1px">Harga</div>
              <div style="font-size:32px;font-weight:700;color:var(--primary)">Rp <?= number_format(floatval($bird['bird_price'] ?? 0), 0, ',', '.') ?></div>
            </div>

            <!-- Penjual Info -->
            <div style="background:#f9f9f9;padding:12px;border-radius:8px;margin-bottom:16px;border-left:4px solid var(--primary)">
              <p style="margin:0 0 4px 0"><strong>👨‍💼 Penjual:</strong> <?= htmlspecialchars($bird['seller_name'] ?? 'Admin') ?></p>
              <?php if ($seller_contact): ?>
                <p style="margin:4px 0;font-size:13px;color:#666">📞 <?= htmlspecialchars($seller_contact) ?></p>
              <?php endif; ?>
            </div>

            <!-- Beli Form -->
            <form method="post" style="margin-bottom:16px">
              <input type="hidden" name="action" value="buy_bird">
              <div class="form-group">
                <label>Jumlah Yang Ingin Dibeli:</label>
                <input type="number" name="qty" value="1" min="1" max="10" required>
              </div>
              <button type="submit" class="btn" style="width:100%;background:var(--success);color:white">✓ Beli Sekarang</button>
            </form>

            <!-- Chat Button -->
            <button onclick="location.href='messages.php?user=<?=htmlspecialchars($bird['uploaded_by'])?>';" class="btn" style="width:100%;background:var(--primary);color:white;margin-bottom:16px">💬 Chat Penjual</button>
            
            <a href="dashboard.php?page=orders" class="btn btn-secondary" style="width:100%;text-align:center">Lihat Pesanan Saya</a>
          </div>
        </div>

        <!-- Ulasan Section -->
        <div class="row" style="margin-top:24px">
          <h2 class="row-title">⭐ Ulasan & Rating</h2>
          
          <?php if ($reviews): ?>
            <div style="margin-bottom:20px">
              <?php foreach ($reviews as $review): ?>
                <div class="review-item">
                  <div class="review-header">
                    <div>
                      <div class="review-author"><?= htmlspecialchars($review['username']) ?></div>
                      <div class="review-rating">★★★★★ <?= $review['rating'] ?>/5</div>
                    </div>
                    <div class="review-date"><?= htmlspecialchars($review['created_at']) ?></div>
                  </div>
                  <div class="review-text"><?= htmlspecialchars($review['comment']) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p style="color:#999;text-align:center;padding:20px">Belum ada ulasan</p>
          <?php endif; ?>

          <!-- Form Tambah Ulasan -->
          <form method="post" style="background:#f9f9f9;padding:16px;border-radius:12px">
            <input type="hidden" name="action" value="add_review">
            
            <div class="form-group">
              <label>Rating:</label>
              <select name="rating" required>
                <option value="5">⭐⭐⭐⭐⭐ 5 - Sangat Memuaskan</option>
                <option value="4">⭐⭐⭐⭐ 4 - Memuaskan</option>
                <option value="3">⭐⭐⭐ 3 - Cukup</option>
                <option value="2">⭐⭐ 2 - Kurang</option>
                <option value="1">⭐ 1 - Tidak Puas</option>
              </select>
            </div>

            <div class="form-group">
              <label>Ulasan Anda:</label>
              <textarea name="comment" placeholder="Bagikan pengalaman Anda tentang burung ini..." required style="min-height:100px;padding:12px;border:2px solid #e0e0e0;border-radius:8px;font-family:inherit;width:100%"></textarea>
            </div>

            <button type="submit" class="btn" style="background:var(--primary);color:white">Kirim Ulasan</button>
          </form>
        </div>

      <?php else: ?>
        <div style="text-align:center;padding:40px;color:#999">
          <p>Burung tidak ditemukan</p>
          <a href="dashboard.php" class="btn">← Kembali ke Galeri</a>
        </div>
      <?php endif; ?>
    </main>

    <footer class="site-footer">
      <div class="footer-inner">🐦 BirdKita - Marketplace & Komunitas Burung Indonesia © 2026</div>
    </footer>
  </div>

  <script>
  </script>
</body>
</html>
