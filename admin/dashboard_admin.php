<?php
session_start();
require_once __DIR__ . '/../config.php';

// proteksi admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$messages = [];
$errors = [];

// =======================
// PROSES FORM
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

// -------- Approve/Reject Seller Listing --------
    if ($action === 'approve_listing' || $action === 'reject_listing') {
        $listing_id = (int)($_POST['listing_id'] ?? 0);
        if ($listing_id) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS seller_listings (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT NOT NULL,
                    `bird_name` VARCHAR(255) NOT NULL,
                    `bird_type` VARCHAR(255),
                    `bird_price` VARCHAR(100),
                    `bird_rank` VARCHAR(100),
                    `description` TEXT,
                    `image_path` VARCHAR(255),
                    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                    `rejection_reason` TEXT,
                    `created_at` DATETIME,
                    `updated_at` DATETIME,
                    KEY `user_id` (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (Exception $e) {}
            
            if ($action === 'approve_listing') {
                // Ambil data listing
                $stmt = $pdo->prepare('SELECT * FROM seller_listings WHERE id = ?');
                $stmt->execute([$listing_id]);
                $listing = $stmt->fetch();
                
                if ($listing) {
                    // Pindahkan ke tabel winners
                    $pdo->exec("CREATE TABLE IF NOT EXISTS winners (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        bird_name VARCHAR(255),
                        bird_type VARCHAR(255),
                        bird_price VARCHAR(100),
                        image_path VARCHAR(255),
                        bird_rank VARCHAR(50),
                        uploaded_by INT,
                        created_at DATETIME
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    
                    $stmt = $pdo->prepare('INSERT INTO winners (bird_name, bird_type, bird_price, image_path, bird_rank, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
                    $stmt->execute([
                        $listing['bird_name'],
                        $listing['bird_type'],
                        $listing['bird_price'],
                        $listing['image_path'],
                        $listing['bird_rank'],
                        $_SESSION['user']['id']
                    ]);
                    
                    // Update status listing
                    $stmt = $pdo->prepare('UPDATE seller_listings SET status = ?, updated_at = NOW() WHERE id = ?');
                    $stmt->execute(['approved', $listing_id]);
                    
                    $messages[] = 'Listing disetujui dan dipublikasi ke galeri utama';
                }
            } else {
                $reason = trim($_POST['rejection_reason'] ?? '');
                $stmt = $pdo->prepare('UPDATE seller_listings SET status = ?, rejection_reason = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute(['rejected', $reason, $listing_id]);
                $messages[] = 'Listing ditolak';
            }
        }
    }

    // -------- Tambah Admin Baru --------
    if ($action === 'create_admin') {
        $u = trim($_POST['username'] ?? '');
        $p = $_POST['password'] ?? '';

        if (strlen($u) < 3) $errors[] = 'Username minimal 3 karakter';
        if (strlen($p) < 6) $errors[] = 'Password minimal 6 karakter';

        if (!$errors) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$u]);
            if ($stmt->fetch()) {
                $errors[] = 'Username sudah ada';
            } else {
                $hash = password_hash($p, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, created_at) VALUES (?, ?, ?, NOW())');
                $stmt->execute([$u, $hash, 'admin']);
                $messages[] = 'Admin baru berhasil ditambahkan';
            }
        }
    }

// -------- Upload Burung --------
if ($action === 'upload_winner') {
    $bird = trim($_POST['bird_name'] ?? '');
    $jenis = trim($_POST['bird_type'] ?? '');
    $harga = trim($_POST['bird_price'] ?? '');
    $rank = trim($_POST['rank'] ?? '');

    if ($bird === '') $errors[] = 'Nama burung harus diisi';
    if ($jenis === '') $errors[] = 'Jenis burung harus diisi';
    if ($harga === '') $errors[] = 'Harga harus diisi';
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) $errors[] = 'Upload foto gagal';

    if (!$errors) {
        $up = $_FILES['photo'];
        $allowed = ['image/jpeg','image/png','image/webp'];
        if (!in_array($up['type'], $allowed, true)) {
            $errors[] = 'Tipe file tidak diizinkan';
        } else {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext = pathinfo($up['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $dest = $uploadDir . $filename;

            if (move_uploaded_file($up['tmp_name'], $dest)) {
                // Perbaikan CREATE TABLE
                $pdo->exec("CREATE TABLE IF NOT EXISTS winners (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    bird_name VARCHAR(255),
                    bird_type VARCHAR(255),
                    bird_price VARCHAR(100),
                    image_path VARCHAR(255),
                    bird_rank VARCHAR(50),
                    uploaded_by INT,
                    created_at DATETIME
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                // Insert data ke tabel
                $stmt = $pdo->prepare('INSERT INTO winners (bird_name, bird_type, bird_price, image_path, bird_rank, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
                $relpath = 'uploads/' . $filename;
                $stmt->execute([$bird, $jenis, $harga, $relpath, $rank, $_SESSION['user']['id']]);
                $messages[] = 'Burung berhasil di-upload';
            } else {
                $errors[] = 'Gagal memindahkan file';
            }
        }
    }
}

// Daftar burung (fetch dari tabel winners)
$winners = $pdo->query('SELECT * FROM winners ORDER BY created_at DESC')->fetchAll();

    // -------- Konfirmasi / Tolak Pesanan --------
    if ($action === 'confirm_order' || $action === 'reject_order') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        if ($order_id) {
            $status = ($action === 'confirm_order') ? 'Confirmed' : 'Rejected';
            $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $stmt->execute([$status, $order_id]);
            $messages[] = ($status === 'Confirmed') ? 'Pesanan telah dikonfirmasi' : 'Pesanan ditolak';
        }
    }
}

// =======================
// Ambil Statistik
// =======================
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAdmins = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$totalNonAdmin = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();

// Seller listings
$pendingListings = [];
try {
    $stmt = $pdo->query("CREATE TABLE IF NOT EXISTS seller_listings (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `bird_name` VARCHAR(255) NOT NULL,
        `bird_type` VARCHAR(255),
        `bird_price` VARCHAR(100),
        `bird_rank` VARCHAR(100),
        `description` TEXT,
        `image_path` VARCHAR(255),
        `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        `rejection_reason` TEXT,
        `created_at` DATETIME,
        `updated_at` DATETIME,
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $stmt = $pdo->prepare('SELECT sl.*, u.username FROM seller_listings sl JOIN users u ON sl.user_id = u.id WHERE sl.status = ? ORDER BY sl.created_at ASC');
    $stmt->execute(['pending']);
    $pendingListings = $stmt->fetchAll();
} catch (Exception $e) {}

$totalListings = 0;
$totalSellers = 0;
$totalApprovedListings = 0;
try {
    $totalListings = $pdo->query("SELECT COUNT(*) FROM seller_listings WHERE status = 'approved'")->fetchColumn();
    $totalApprovedListings = $totalListings;
    $totalSellers = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM seller_listings WHERE status = 'approved'")->fetchColumn();
} catch (Exception $e) {}

// Daftar burung
$winners = $pdo->query('SELECT * FROM winners ORDER BY created_at DESC')->fetchAll();

// Daftar pesanan
$orders = $pdo->query('SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Dashboard - BirdKita</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
  <div class="site-wrap">
    <header class="site-header">
      <div class="nav-inner">
        <div class="brand">
          <img src="../assets/logo.svg" alt="Logo" class="logo">
          <span class="brand-title">BirdKita</span>
        </div>
        <div style="flex:1"></div>
        <div class="user-actions">
          <a href="../dashboard.php" class="btn" style="background:var(--accent);color:var(--text-dark)">← User Dashboard</a>
          <a href="../logout.php" class="logout">Logout</a>
        </div>
      </div>
    </header>

    <main class="main">
      <div class="admin-header">
        <div>
          <h1 style="margin:0;color:var(--primary)">⚙️ Admin Dashboard</h1>
          <p style="margin:4px 0 0 0;color:#666;font-size:14px">Selamat datang, <?=htmlspecialchars($_SESSION['user']['username'])?>!</p>
        </div>
      </div>

      <?php if ($errors): ?>
        <div class="errors" style="margin-bottom:20px">
          <?php foreach ($errors as $err): ?>
            ✗ <?=htmlspecialchars($err)?><br>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($messages): ?>
        <div class="messages" style="margin-bottom:20px">
          <?php foreach ($messages as $msg): ?>
            ✓ <?=htmlspecialchars($msg)?><br>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- ===== STATISTIK ===== -->
      <div class="admin-stats">
        <div class="stat-card">
          <div class="stat-value"><?=htmlspecialchars($totalUsers)?></div>
          <div class="stat-label">Total Pengguna</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?=htmlspecialchars($totalAdmins)?></div>
          <div class="stat-label">Admin</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?=htmlspecialchars($totalNonAdmin)?></div>
          <div class="stat-label">Member Regular</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?=count($pendingListings)?></div>
          <div class="stat-label">Listing Menunggu</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?=htmlspecialchars($totalSellers)?></div>
          <div class="stat-label">Total Penjual</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?=htmlspecialchars($totalApprovedListings)?></div>
          <div class="stat-label">Total Listing Aktif</div>
        </div>
      </div>

      <!-- ===== SELLER LISTINGS APPROVAL ===== -->
      <div class="admin-section">
        <h2>🔔 Persetujuan Listing Penjual (<?=count($pendingListings)?>)</h2>
        
        <?php if (!$pendingListings): ?>
          <p style="color:#999;text-align:center;padding:20px">Tidak ada listing yang menunggu persetujuan.</p>
        <?php else: ?>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
            <?php foreach ($pendingListings as $listing): ?>
              <div style="background:white;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);border:2px solid var(--warning)">
                <!-- Gambar -->
                <div style="height:180px;background:#f0f0f0;overflow:hidden">
                  <?php if ($listing['image_path'] && file_exists(__DIR__ . '/../' . $listing['image_path'])): ?>
                    <img src="../<?=htmlspecialchars($listing['image_path'])?>" alt="<?=htmlspecialchars($listing['bird_name'])?>" style="width:100%;height:100%;object-fit:cover">
                  <?php else: ?>
                    <img src="../assets/parrot.svg" alt="<?=htmlspecialchars($listing['bird_name'])?>" style="width:100%;height:100%;object-fit:cover">
                  <?php endif; ?>
                </div>
                
                <!-- Info -->
                <div style="padding:16px">
                  <h3 style="margin:0 0 8px 0;color:var(--primary);font-size:18px"><?=htmlspecialchars($listing['bird_name'])?></h3>
                  
                  <div style="font-size:13px;color:#666;margin-bottom:12px">
                    <p style="margin:0"><strong>Jenis:</strong> <?=htmlspecialchars($listing['bird_type'])?></p>
                    <p style="margin:4px 0"><strong>Harga:</strong> Rp <?=number_format(floatval($listing['bird_price']), 0, ',', '.')?></p>
                    <p style="margin:4px 0"><strong>Penjual:</strong> <?=htmlspecialchars($listing['username'])?></p>
                    <?php if ($listing['description']): ?>
                      <p style="margin:4px 0;font-size:12px;color:#999"><strong>Deskripsi:</strong> <?=substr(htmlspecialchars($listing['description']), 0, 50)?>...</p>
                    <?php endif; ?>
                  </div>
                  
                  <!-- Actions -->
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px">
                    <form method="post" style="margin:0">
                      <input type="hidden" name="action" value="approve_listing">
                      <input type="hidden" name="listing_id" value="<?=$listing['id']?>">
                      <button type="submit" class="btn" style="width:100%;background:var(--success);color:white;padding:8px;font-size:12px">✓ Setujui</button>
                    </form>
                    <form method="post" style="margin:0" onsubmit="return showRejectForm(<?=$listing['id']?>);">
                      <input type="hidden" name="action" value="reject_listing">
                      <input type="hidden" name="listing_id" value="<?=$listing['id']?>">
                      <button type="button" class="btn" style="width:100%;background:var(--danger);color:white;padding:8px;font-size:12px" onclick="showRejectModal(<?=$listing['id']?>)">✗ Tolak</button>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- ===== UPLOAD BURUNG ===== -->
      <div class="admin-section">
        <h2>🐦 Upload Burung Baru</h2>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload_winner">
          
          <div class="form-row">
            <div class="form-group">
              <label for="bird_name">Nama Burung *</label>
              <input type="text" id="bird_name" name="bird_name" required placeholder="Contoh: Lovebird Pastel">
            </div>
            
            <div class="form-group">
              <label for="bird_price">Harga (Rp) *</label>
              <input type="number" id="bird_price" name="bird_price" required placeholder="Contoh: 500000">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="bird_type">Jenis Burung *</label>
              <select id="bird_type" name="bird_type" required>
                <option value="">-- Pilih Jenis Burung --</option>
                <option value="Lovebird">🦜 Lovebird</option>
                <option value="Murai">🐦 Murai</option>
                <option value="Kacer">🐦 Kacer</option>
                <option value="Cucak Hijau">🌿 Cucak Hijau</option>
                <option value="Burung Hantu">🦉 Burung Hantu</option>
                <option value="Parkit">🦜 Parkit</option>
                <option value="Elang">🦅 Elang</option>
                <option value="Cendrawasih">✨ Cendrawasih</option>
                <option value="Jalak">🐦 Jalak</option>
                <option value="Perkutut">🕊️ Perkutut</option>
                <option value="Lainnya">❓ Lainnya</option>
              </select>
            </div>

            <div class="form-group">
              <label for="rank">Penghargaan / Keterangan</label>
              <input type="text" id="rank" name="rank" placeholder="Contoh: Juara 1 Lokalpedia">
            </div>
          </div>

          <div class="form-group">
            <label for="photo">📸 Upload Foto *</label>
            <div class="upload-area">
              <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" required style="width:100%;padding:12px">
              <p style="font-size:12px;color:#666;margin:8px 0 0 0">Format: JPG, PNG, WebP (Max 5MB)</p>
            </div>
          </div>

          <button type="submit" class="btn" style="background:var(--success)">✓ Upload Burung</button>
        </form>
      </div>

      <!-- ===== DAFTAR BURUNG ===== -->
      <div class="admin-section">
        <h2><?=count($winners)?>🐦 Data Burung</h2>
        <?php if (!$winners): ?>
          <p style="color:#999;text-align:center;padding:20px">Belum ada burung yang di-upload.</p>
        <?php else: ?>
          <div class="bird-gallery">
            <?php foreach ($winners as $bird): ?>
              <div class="bird-thumb">
                <?php if (!empty($bird['image_path']) && file_exists('../' . $bird['image_path'])): ?>
                  <img src="../<?=htmlspecialchars($bird['image_path'])?>" alt="<?=htmlspecialchars($bird['bird_name'])?>">
                <?php else: ?>
                  <img src="../assets/parrot.svg" alt="<?=htmlspecialchars($bird['bird_name'])?>">
                <?php endif; ?>
                <div style="padding:12px;background:white">
                  <div style="font-weight:700;font-size:13px"><?=htmlspecialchars($bird['bird_name'])?></div>
                  <div style="font-size:12px;color:#666;margin:4px 0">Rp <?=number_format(floatval($bird['bird_price'] ?? 0), 0, ',', '.')?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- ===== TAMBAH ADMIN ===== -->
      <div class="admin-section">
        <h2>👨‍💼 Buat Admin Baru</h2>
        <form method="post" style="max-width:500px">
          <input type="hidden" name="action" value="create_admin">
          
          <div class="form-group">
            <label for="admin_username">Username *</label>
            <input type="text" id="admin_username" name="username" required placeholder="Username minimal 3 karakter" minlength="3">
          </div>

          <div class="form-group">
            <label for="admin_password">Password *</label>
            <input type="password" id="admin_password" name="password" required placeholder="Password minimal 6 karakter" minlength="6">
          </div>

          <button type="submit" class="btn" style="background:var(--warning)">✓ Buat Admin</button>
        </form>
      </div>

      <!-- ===== PESANAN PELANGGAN ===== -->
      <div class="admin-section">
        <h2>📦 Kelola Pesanan (<?=count($orders)?>)</h2>
        
        <?php if (!$orders): ?>
          <p style="color:#999;text-align:center;padding:20px">Belum ada pesanan.</p>
        <?php else: ?>
          <div class="order-list">
            <?php foreach ($orders as $o): ?>
              <div class="order-card <?=strtolower($o['status'])?>">
                <div class="order-header">
                  <div>
                    <div class="order-title"><?=htmlspecialchars($o['bird_name'])?></div>
                    <div style="font-size:12px;color:#666">Jenis: <?=htmlspecialchars($o['bird_type'] ?? '-')?></div>
                    <div style="font-size:12px;color:#666">👤 Pembeli: <strong><?=htmlspecialchars($o['username'])?></strong></div>
                  </div>
                  <span class="order-status <?=strtolower($o['status'])?>">
                    <?=htmlspecialchars($o['status'])?>
                  </span>
                </div>
                
                <div class="order-details">
                  <div>📊 Jumlah: <?=htmlspecialchars($o['quantity'])?> ekor</div>
                  <div>💰 Harga: Rp <?=number_format(floatval($o['bird_price'] ?? 0), 0, ',', '.')?>/ekor</div>
                  <div style="font-weight:700;color:var(--primary);margin-top:4px">
                    💳 Total: Rp <?=number_format(floatval($o['total_price'] ?? 0), 0, ',', '.')?>
                  </div>
                  <div style="font-size:12px;color:#999;margin-top:6px">📅 <?=htmlspecialchars($o['created_at'])?></div>
                </div>

                <?php if ($o['status'] === 'Pending'): ?>
                  <div class="order-actions">
                    <form method="post" style="margin:0;flex:1">
                      <input type="hidden" name="action" value="confirm_order">
                      <input type="hidden" name="order_id" value="<?=htmlspecialchars($o['id'])?>">
                      <button type="submit" class="order-actions button" style="background:var(--success);color:white">✓ Konfirmasi</button>
                    </form>
                    <form method="post" style="margin:0;flex:1">
                      <input type="hidden" name="action" value="reject_order">
                      <input type="hidden" name="order_id" value="<?=htmlspecialchars($o['id'])?>">
                      <button type="submit" class="order-actions button" style="background:var(--danger);color:white">✗ Tolak</button>
                    </form>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </main>

    <footer class="site-footer">
      <div class="footer-inner">🐦 BirdKita Admin - Kelola Marketplace Burung Indonesia © 2026</div>
    </footer>
  </div>

  <script>
    function showRejectModal(listingId) {
      const reason = prompt('Masukkan alasan penolakan listing:', '');
      if (reason !== null) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="action" value="reject_listing">
          <input type="hidden" name="listing_id" value="${listingId}">
          <input type="hidden" name="rejection_reason" value="${reason.replace(/"/g, '&quot;')}">
        `;
        document.body.appendChild(form);
        form.submit();
      }
    }
  </script>
</body>
</html>
