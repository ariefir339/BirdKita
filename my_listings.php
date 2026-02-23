<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user']['id'];
$message = '';

// Create seller_listings table if not exists
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

// Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Upload listing
    if ($action === 'upload_listing') {
        $bird_name = trim($_POST['bird_name'] ?? '');
        $bird_type = trim($_POST['bird_type'] ?? '');
        $bird_price = trim($_POST['bird_price'] ?? '');
        $bird_rank = trim($_POST['bird_rank'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        $file_path = '';
        
        // Handle file upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $file_info = pathinfo($_FILES['image']['name']);
            $file_ext = strtolower($file_info['extension']);
            
            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $file_name = 'seller_' . uniqid() . '.' . $file_ext;
                $file_path = 'uploads/' . $file_name;
                
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0755, true);
                }
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $file_path)) {
                    // OK
                } else {
                    $message = '❌ Gagal upload gambar';
                    $file_path = '';
                }
            } else {
                $message = '❌ Format gambar tidak didukung (gunakan jpg, png, gif)';
            }
        }
        
        if ($bird_name && $bird_type && $bird_price) {
            try {
                $stmt = $pdo->prepare('INSERT INTO seller_listings (user_id, bird_name, bird_type, bird_price, bird_rank, description, image_path, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
                $stmt->execute([$user_id, $bird_name, $bird_type, $bird_price, $bird_rank, $description, $file_path]);
                $message = '✓ Listing dibuat! Tunggu persetujuan admin.';
            } catch (Exception $e) {
                $message = '❌ Error: ' . $e->getMessage();
            }
        } else {
            $message = '❌ Isi semua field yang wajib';
        }
    }
    
    // Delete listing (hanya status pending)
    if ($action === 'delete_listing') {
        $listing_id = (int)($_POST['listing_id'] ?? 0);
        try {
            $stmt = $pdo->prepare('SELECT status, user_id FROM seller_listings WHERE id = ?');
            $stmt->execute([$listing_id]);
            $listing = $stmt->fetch();
            
            if ($listing && $listing['user_id'] == $user_id && $listing['status'] === 'pending') {
                $stmt = $pdo->prepare('DELETE FROM seller_listings WHERE id = ?');
                $stmt->execute([$listing_id]);
                $message = '✓ Listing dihapus';
            } else {
                $message = '❌ Listing tidak bisa dihapus';
            }
        } catch (Exception $e) {
            $message = '❌ Error: ' . $e->getMessage();
        }
    }
}

// Get user's listings
$listings = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM seller_listings WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$user_id]);
    $listings = $stmt->fetchAll();
} catch (Exception $e) {}

// Get status counts
$pending_count = 0;
$approved_count = 0;
foreach ($listings as $l) {
    if ($l['status'] === 'pending') $pending_count++;
    else if ($l['status'] === 'approved') $approved_count++;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Daftar Jual - BirdKita</title>
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
          <a href="logout.php" class="logout">Logout</a>
        </div>
      </div>
    </header>

    <main class="main">

<div class="user-actions">
          <a href="dashboard.php" class="btn" style="background:var(--accent);color:var(--text-dark)">← Kembali</a>
        </div>

<br>

      <?php if ($message): ?>
        <div class="messages" style="margin-bottom:20px">
          <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>

      <!-- Stats -->
      <div class="row" style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px">
        <div style="background:var(--accent);padding:16px;border-radius:12px;text-align:center;color:var(--text-dark)">
          <div style="font-size:24px;font-weight:700"><?= count($listings) ?></div>
          <div style="font-size:13px;text-transform:uppercase;letter-spacing:1px">Total Listing</div>
        </div>
        <div style="background:var(--warning);padding:16px;border-radius:12px;text-align:center;color:white">
          <div style="font-size:24px;font-weight:700"><?= $pending_count ?></div>
          <div style="font-size:13px;text-transform:uppercase;letter-spacing:1px">Menunggu Persetujuan</div>
        </div>
        <div style="background:var(--success);padding:16px;border-radius:12px;text-align:center;color:white">
          <div style="font-size:24px;font-weight:700"><?= $approved_count ?></div>
          <div style="font-size:13px;text-transform:uppercase;letter-spacing:1px">Sudah Dipublikasi</div>
        </div>
      </div>

      <!-- Form Upload -->
      <div class="row">
        <h2 class="row-title">➕ Jual Burung Baru</h2>
        
        <form method="post" enctype="multipart/form-data" style="background:#f9f9f9;padding:20px;border-radius:12px">
          <input type="hidden" name="action" value="upload_listing">
          
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
              <label>Nama Burung *</label>
              <input type="text" name="bird_name" placeholder="Contoh: Lovebird biru" required>
            </div>
            
            <div class="form-group">
              <label>Jenis Burung *</label>
              <select name="bird_type" required>
                <option value="">Pilih jenis</option>
                <option value="Lovebird">Lovebird</option>
                <option value="Parrot">Parrot</option>
                <option value="Kakatua">Kakatua</option>
                <option value="Murai Batu">Murai Batu</option>
                <option value="Cucak Rante">Cucak Rante</option>
                <option value="Cendet">Cendet</option>
                <option value="Burung Hantu">Burung Hantu</option>
                <option value="Lainnya">Lainnya</option>
              </select>
            </div>
            
            <div class="form-group">
              <label>Harga (Rp) *</label>
              <input type="number" name="bird_price" placeholder="500000" step="10000" required>
            </div>
            
            <div class="form-group">
              <label>Penghargaan/Prestasi</label>
              <input type="text" name="bird_rank" placeholder="Contoh: Juara Nasional 2024">
            </div>
          </div>
          
          <div class="form-group">
            <label>Deskripsi Burung</label>
            <textarea name="description" placeholder="Jelaskan kondisi, usia, karakter burung Anda..." style="min-height:120px;padding:12px;border:2px solid #e0e0e0;border-radius:8px;font-family:inherit;width:100%"></textarea>
          </div>
          
          <div class="form-group">
            <label>Foto Burung</label>
            <div style="background:white;padding:16px;border:2px dashed var(--primary);border-radius:8px;text-align:center;cursor:pointer" onclick="document.getElementById('image_input').click()">
              <input type="file" id="image_input" name="image" accept="image/*" style="display:none" onchange="showFileName(this)">
              <div id="file_label" style="color:#666">📸 Klik atau tarik gambar Anda di sini</div>
            </div>
          </div>
          
          <button type="submit" class="btn" style="background:var(--primary);color:white;width:100%">🚀 Unggah Listing</button>
        </form>
      </div>

      <!-- Daftar Listing -->
      <div class="row" style="margin-top:24px">
        <h2 class="row-title">📋 Listing Saya (<?= count($listings) ?>)</h2>
        
        <?php if ($listings): ?>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
            <?php foreach ($listings as $listing): ?>
              <div style="background:white;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08)">
                <!-- Gambar -->
                <div style="height:180px;background:#f0f0f0;overflow:hidden">
                  <?php if ($listing['image_path'] && file_exists($listing['image_path'])): ?>
                    <img src="<?= htmlspecialchars($listing['image_path']) ?>" alt="<?= htmlspecialchars($listing['bird_name']) ?>" style="width:100%;height:100%;object-fit:cover">
                  <?php else: ?>
                    <img src="assets/parrot.svg" alt="<?= htmlspecialchars($listing['bird_name']) ?>" style="width:100%;height:100%;object-fit:cover">
                  <?php endif; ?>
                </div>
                
                <!-- Info -->
                <div style="padding:16px">
                  <h3 style="margin:0 0 8px 0;color:var(--primary);font-size:18px"><?= htmlspecialchars($listing['bird_name']) ?></h3>
                  
                  <p style="margin:0;font-size:13px;color:#666">
                    <strong>Jenis:</strong> <?= htmlspecialchars($listing['bird_type']) ?><br>
                    <strong>Harga:</strong> Rp <?= number_format(floatval($listing['bird_price']), 0, ',', '.') ?>
                  </p>
                  
                  <!-- Status -->
                  <div style="margin:12px 0">
                    <?php if ($listing['status'] === 'pending'): ?>
                      <span style="background:#ffc107;color:white;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;text-transform:uppercase">⏳ Menunggu Persetujuan</span>
                    <?php elseif ($listing['status'] === 'approved'): ?>
                      <span style="background:var(--success);color:white;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;text-transform:uppercase">✓ Dipublikasi</span>
                    <?php else: ?>
                      <span style="background:#d32f2f;color:white;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;text-transform:uppercase">✕ Ditolak</span>
                      <?php if ($listing['rejection_reason']): ?>
                        <div style="background:#ffebee;padding:8px;border-radius:6px;margin-top:8px;font-size:12px;color:#c62828">
                          <strong>Alasan:</strong> <?= htmlspecialchars($listing['rejection_reason']) ?>
                        </div>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                  
                  <!-- Actions -->
                  <?php if ($listing['status'] === 'pending'): ?>
                    <form method="post" style="margin-top:12px">
                      <input type="hidden" name="action" value="delete_listing">
                      <input type="hidden" name="listing_id" value="<?= $listing['id'] ?>">
                      <button type="submit" class="btn btn-secondary" style="width:100%;font-size:13px;padding:8px" onclick="return confirm('Hapus listing ini?')">🗑️ Hapus</button>
                    </form>
                  <?php elseif ($listing['status'] === 'approved'): ?>
                    <div style="margin-top:12px">
                      <a href="bird_detail.php?id=<?= $listing['id'] ?>" class="btn" style="display:block;text-align:center;background:var(--primary);color:white;text-decoration:none;padding:8px;border-radius:6px;font-size:13px">👁️ Lihat Detail</a>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div style="text-align:center;padding:40px;background:#f9f9f9;border-radius:12px;color:#999">
            <p style="font-size:18px">Belum ada listing</p>
            <p style="font-size:13px">Mulai jual burung Anda sekarang →</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Info -->
      <div class="row" style="margin-top:24px;background:#e8f5e9;padding:16px;border-radius:12px;border-left:4px solid var(--success)">
        <h3 style="margin-top:0;color:var(--success)">ℹ️ Cara Kerja Sistem Penjualan</h3>
        <ol style="margin:0;padding-left:20px;color:#333;line-height:1.8">
          <li><strong>Unggah burung Anda</strong> dengan foto, deskripsi, dan harga</li>
          <li><strong>Tunggu persetujuan admin</strong> (maksimal 24 jam)</li>
          <li><strong>Listing dipublikasi</strong> dan bisa dibeli pembeli</li>
          <li><strong>Chat dengan pembeli</strong> dan arrange pengiriman</li>
          <li><strong>Dapatkan rating & review</strong> dari pembeli</li>
        </ol>
      </div>
    </main>

    <footer class="site-footer">
      <div class="footer-inner">🐦 BirdKita - Marketplace & Komunitas Burung Indonesia © 2026</div>
    </footer>
  </div>

  <script>
    function showFileName(input) {
      if (input.files && input.files[0]) {
        document.getElementById('file_label').textContent = '✓ ' + input.files[0].name;
      }
    }
  </script>
</body>
</html>
