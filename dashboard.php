<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// tentukan page yang ditampilkan
$page = $_GET['page'] ?? 'gallery';
if (!in_array($page, ['gallery', 'orders', 'profile'], true)) {
    $page = 'gallery';
}

// proses pembelian
$buyMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'buy_bird') {
    $bird_id = (int)($_POST['bird_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 1);
    if ($bird_id && $qty > 0) {
        // buat tabel orders jika belum ada
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
        
        // get bird data
        $stmt = $pdo->prepare('SELECT * FROM winners WHERE id = ?');
        $stmt->execute([$bird_id]);
        $bird = $stmt->fetch();
        
        if ($bird) {
            $total = floatval($bird['bird_price'] ?? 0) * $qty;
            $stmt = $pdo->prepare('INSERT INTO orders (`user_id`, `bird_id`, `bird_name`, `bird_type`, `bird_price`, `quantity`, `total_price`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$_SESSION['user']['id'], $bird_id, $bird['bird_name'], $bird['bird_type'], $bird['bird_price'], $qty, $total]);
            $buyMessage = 'Pembelian berhasil! Status: Pending. Admin akan mengkonfirmasi pesanan Anda.';
        }
    }
}

// ambil data burung dari database
$birds = [];
try {
    $stmt = $pdo->query('SELECT * FROM winners ORDER BY created_at DESC');
    $birds = $stmt->fetchAll();
} catch (Exception $e) {
    // tabel mungkin belum ada, biarkan kosong
}

$birds = [];

$stmt = $pdo->query("
SELECT *
FROM winners
ORDER BY bird_type ASC, created_at DESC
");

$birds = $stmt->fetchAll(PDO::FETCH_ASSOC);

$birdsByType = [];

foreach ($birds as $bird) {

    $type = strtolower(trim($bird['bird_type'] ?? 'lainnya'));

    if ($type == '') {
        $type = 'lainnya';
    }

    if (!isset($birdsByType[$type])) {
        $birdsByType[$type] = [];
    }

    $birdsByType[$type][] = $bird;
}

// ambil pesanan user
$orders = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$_SESSION['user']['id']]);
    $orders = $stmt->fetchAll();
} catch (Exception $e) {
    // tabel mungkin belum ada
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $page === 'gallery' ? 'Galeri' : ($page === 'orders' ? 'Pesanan' : 'Profil') ?> - BirdKita</title>
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

        <button class="hamburger" id="hamburgerBtn" aria-label="Toggle menu">
          <span></span>
          <span></span>
          <span></span>
        </button>

        <div class="search" id="searchContainer">
          <input type="search" id="search" placeholder="Cari burung...">
        </div>

        <div class="user-actions" id="userActionsMenu">
          <?php if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin'): ?>
            <a href="admin/dashboard_admin.php" class="btn" style="background:var(--warning)">Admin Panel</a>
          <?php endif; ?>
          <a href="logout.php" class="logout">Logout</a>
        </div>
      </div>

      <div class="header-bottom" id="navMenu">
        <div class="header-circles">
          <a href="dashboard.php?page=gallery" class="circle-btn <?= $page === 'gallery' ? 'active' : '' ?>" title="Galeri">📋</a>
          <a href="dashboard.php?page=orders" class="circle-btn <?= $page === 'orders' ? 'active' : '' ?>" title="Pesanan">📦</a>
          <a href="messages.php" class="circle-btn" title="Chat">💬</a>
          <a href="my_listings.php" class="circle-btn" title="Jual Burung">💼</a>
          <a href="dashboard.php?page=profile" class="circle-btn <?= $page === 'profile' ? 'active' : '' ?>" title="Profil">👤</a>
        </div>
      </div>
    </header>

    <main class="main">
      <div class="welcome">
        <h2>Selamat datang, <?= htmlspecialchars($_SESSION['user']['username']) ?> 👋</h2>
      </div>

      <?php if ($buyMessage): ?>
        <div class="messages" style="margin-bottom:20px">
          <?=htmlspecialchars($buyMessage)?>
        </div>
      <?php endif; ?>

      <div class="dashboard">

        <!-- ===== HALAMAN GALERI ===== -->
        <?php if ($page === 'gallery'): ?>
          <div class="row">
            <h3>🐦 Galeri Burung</h3>
            
            <?php if (!$birds): ?>
              <div style="text-align:center;padding:40px;color:#999">
                <p style="font-size:16px;margin:0">Belum ada burung untuk dijual :(</p>
              </div>
            <?php else: ?>
              <!-- Filter Tabs -->
              <div class="filter-tabs">
                <button class="filter-btn active" data-filter="all">Semua</button>
                <?php foreach ($birdsByType as $type => $birdsOfType): ?>
                  <button class="filter-btn" data-filter="<?=htmlspecialchars($type)?>">
                    <?=htmlspecialchars(ucfirst($type))?>
                  </button>
                <?php endforeach; ?>
              </div>

              <!-- Gallery Grid -->
              <div class="gallery-grid">
                <?php foreach ($birdsByType as $type => $birdsOfType): ?>
                  <div class="gallery-section" data-type="<?=htmlspecialchars($type)?>">
                    <h4><?=htmlspecialchars(ucfirst($type))?></h4>
                    <div class="carousel" tabindex="0">
                      <button class="nav left" aria-label="Lihat sebelumnya">‹</button>
                      <div class="list">
                        <?php foreach ($birdsOfType as $bird): ?>
                          <a href="bird_detail.php?id=<?=$bird['id']?>" class="item" style="cursor:pointer;text-decoration:none;color:inherit">
                            <?php if (!empty($bird['image_path']) && file_exists($bird['image_path'])): ?>
                              <img src="<?=htmlspecialchars($bird['image_path'])?>" alt="<?=htmlspecialchars($bird['bird_name'])?>">
                            <?php else: ?>
                              <img src="assets/parrot.svg" alt="<?=htmlspecialchars($bird['bird_name'])?>">
                            <?php endif; ?>
                            <div class="caption">
                              <strong><?=htmlspecialchars($bird['bird_name'])?></strong>
                              <div style="font-size:11px;color:#666;margin:2px 0"><?=htmlspecialchars($bird['bird_type'] ?? '-')?></div>
                              <div style="font-size:13px;font-weight:bold;color:var(--primary)">Rp <?=number_format(floatval($bird['bird_price'] ?? 0), 0, ',', '.')?></div>
                            </div>
                          </a>
                        <?php endforeach; ?>
                      </div>
                      <button class="nav right" aria-label="Lihat selanjutnya">›</button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <!-- ===== HALAMAN PESANAN ===== -->
        <?php elseif ($page === 'orders'): ?>
          <div class="row">
            <h2 class="row-title">📦 Riwayat Pesanan Saya</h2>
            
            <?php if (!$orders): ?>
              <div style="text-align:center;padding:40px;color:#999">
                <p style="font-size:16px;margin:0">Anda belum memiliki pesanan</p>
              </div>
            <?php else: ?>
              <div class="order-list">
                <?php foreach ($orders as $order): ?>
                  <div class="order-card <?=strtolower($order['status'])?>">
                    <div class="order-header">
                      <div class="order-title"><?=htmlspecialchars($order['bird_name'])?></div>
                      <span class="order-status <?=strtolower($order['status'])?>">
                        <?=htmlspecialchars($order['status'])?>
                      </span>
                    </div>
                    <div class="order-details">
                      <div>📌 Jenis: <?=htmlspecialchars($order['bird_type'] ?? '-')?></div>
                      <div>📊 Jumlah: <?=htmlspecialchars($order['quantity'])?> ekor @ Rp <?=number_format(floatval($order['bird_price'] ?? 0), 0, ',', '.')?>/ekor</div>
                      <div style="font-weight:600;color:var(--primary);margin-top:4px">💰 Total: Rp <?=number_format(floatval($order['total_price'] ?? 0), 0, ',', '.')?></div>
                      <div style="font-size:12px;color:#999;margin-top:6px">📅 <?=htmlspecialchars($order['created_at'])?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

        <!-- ===== HALAMAN PROFIL ===== -->
        <?php elseif ($page === 'profile'): ?>
          <div class="row">
            <h2 class="row-title">👤 Profil Saya</h2>
            
            <div class="profile-card">
              <div class="profile-field">
                <label>Username</label>
                <div class="profile-field-value"><?=htmlspecialchars($_SESSION['user']['username'])?></div>
              </div>

              <div class="profile-field">
                <label>User ID</label>
                <div class="profile-field-value"><?=htmlspecialchars($_SESSION['user']['id'])?></div>
              </div>

              <div class="profile-field">
                <label>Status</label>
                <div class="profile-field-value">
                  <span class="badge <?=$_SESSION['user']['role'] === 'admin' ? 'badge-admin' : 'badge-user'?>">
                    <?=htmlspecialchars($_SESSION['user']['role'] ?? 'user')?>
                  </span>
                </div>
              </div>

              <div class="profile-field">
                <label>Total Pesanan</label>
                <div class="profile-field-value"><?=count($orders)?> pesanan</div>
              </div>

              <div style="margin-top:24px;padding-top:16px;border-top:2px solid #e0e0e0">
                <p style="font-size:13px;color:#666;margin:0">
                  ✓ Member sejak: <strong><?=htmlspecialchars(date('d F Y'))?></strong>
                </p>
              </div>
            </div>
          </div>

        <?php endif; ?>

    </main>      <footer class="site-footer">
        <div class="footer-inner">🐦 BirdKita - Marketplace & Komunitas Burung Indonesia © 2026</div>
      </footer>
  </div>

  <script>
    // Hamburger Menu Toggle
    const hamburger = document.getElementById('hamburgerBtn');
    const navMenu = document.getElementById('navMenu');
    const searchContainer = document.getElementById('searchContainer');
    const userActionsMenu = document.getElementById('userActionsMenu');

    hamburger.addEventListener('click', function() {
      this.classList.toggle('active');
      navMenu.classList.toggle('mobile-menu');
      searchContainer.classList.toggle('mobile-menu');
      userActionsMenu.classList.toggle('mobile-menu');
    });

    // Close menu when clicking on a link
    document.querySelectorAll('.circle-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        hamburger.classList.remove('active');
        navMenu.classList.remove('mobile-menu');
        searchContainer.classList.remove('mobile-menu');
        userActionsMenu.classList.remove('mobile-menu');
      });
    });

    // Carousel navigation
    <?php if ($page === 'gallery'): ?>
    document.querySelectorAll('.carousel').forEach(function(carousel) {
      const list = carousel.querySelector('.list');
      const leftBtn = carousel.querySelector('.nav.left');
      const rightBtn = carousel.querySelector('.nav.right');

      function updateArrows() {
        const isAtStart = list.scrollLeft <= 0;
        const isAtEnd = Math.ceil(list.scrollLeft + list.clientWidth) >= list.scrollWidth - 5;
        
        leftBtn.classList.toggle('disabled', isAtStart);
        rightBtn.classList.toggle('disabled', isAtEnd);
      }

      leftBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (!leftBtn.classList.contains('disabled')) {
          list.scrollBy({ left: -260, behavior: 'smooth' });
          setTimeout(updateArrows, 250);
        }
      });

      rightBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (!rightBtn.classList.contains('disabled')) {
          list.scrollBy({ left: 260, behavior: 'smooth' });
          setTimeout(updateArrows, 250);
        }
      });

      list.addEventListener('scroll', updateArrows);
      window.addEventListener('resize', updateArrows);
      updateArrows();
    });

    // Filter functionality
    const filterBtns = document.querySelectorAll('.filter-btn');
    const gallerySections = document.querySelectorAll('.gallery-section');

    filterBtns.forEach(btn => {
      btn.addEventListener('click', function() {
        const filter = this.getAttribute('data-filter');
        
        // Update active button
        filterBtns.forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        // Show/hide sections
        gallerySections.forEach(section => {
          const type = section.getAttribute('data-type');
          if (filter === 'all' || type === filter) {
            section.style.display = '';
          } else {
            section.style.display = 'none';
          }
        });
      });
    });

    // Search filter
    const searchInput = document.getElementById('search');
    if (searchInput) {
      searchInput.addEventListener('input', function(e) {
        const q = e.target.value.trim().toLowerCase();
        document.querySelectorAll('.carousel .item').forEach(function(item) {
          const txt = (item.querySelector('.caption') || {}).textContent || '';
          item.style.display = (!q || txt.toLowerCase().includes(q)) ? '' : 'none';
        });
      });
    }
    <?php endif; ?>
  </script>
</body>
</html>