<?php
require_once 'config.php';

// Inisialisasi variabel
$error_message = '';
$success_message = '';
$show_qr = false;
$order_total = 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle contact form
    if (isset($_POST['submit_pesan'])) {
        $nama = $_POST['nama'];
        $email = $_POST['email'];
        $pesan = $_POST['pesan'];
        
        if (simpanPesan($pdo, $nama, $email, $pesan)) {
            $success_message = "Pesan berhasil dikirim!";
        } else {
            $error_message = "Gagal mengirim pesan!";
        }
    }
    
    // Handle order processing
    if (isset($_POST['process_order'])) {
        try {
            $pdo->beginTransaction();
            
            // Validasi data cart
            if (empty($_POST['cart_data'])) {
                throw new Exception("Data keranjang tidak valid");
            }
            
            $cart = json_decode($_POST['cart_data'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Format data keranjang tidak valid");
            }
            
            // Proses setiap item
            $total = 0;
            foreach ($cart as $item) {
                if (!isset($item['id'], $item['quantity'], $item['price'])) {
                    continue; // Skip data tidak valid
                }
                
                $id = (int)$item['id'];
                $quantity = (int)$item['quantity'];
                $price = (float)$item['price'];
                
                // Validasi stok
                $stmt = $pdo->prepare("UPDATE menu SET stok = stok - ? WHERE id = ? AND stok >= ?");
                $stmt->execute([$quantity, $id, $quantity]);

                echo "Item ID: $id | Jumlah beli: $quantity <br>";
                
                if ($stmt->rowCount() === 0) {
                    throw new Exception("Stok tidak mencukupi untuk item ID: $id");
                }
                
                $total += $price * $quantity;
            }
            
            $pdo->commit();
            $order_total = $total;
            $show_qr = true;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Gagal memproses pesanan: " . $e->getMessage();
        }
    }
}

$kantins = getKantin($pdo);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>kantin SMK Telkom Jakarta</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section {
    background: url('img/kantin.jpg') no-repeat center center;
    font-family: 'Poppins', sans-serif;
    background-size: cover;
    color: white;
    padding: 80px 0;
    }
    </style>
</head>
<form id="order-form" method="POST" style="display: none;">
    <input type="hidden" name="process_order" value="1">
    <input type="hidden" name="cart_data" id="cart-data">
</form>
<body>
    <!-- Navigation -->
   <nav class="navbar navbar-expand-lg navbar-dark bg-danger fixed-top">
    <div class="container">
        <a class="navbar-brand" href="#home">
            <i class="fas fa-utensils"></i> Kantin SMK Telkom
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="navbar-nav ms-auto"> 
                <a class="nav-link" href="#cafetaria">Cafetaria List</a>
                <a class="nav-link" href="#about">About Kantin</a>
                <a class="nav-link" href="#howtobuy">How to Buy</a>
                <a class="nav-link" href="#contact">Contact Us</a>
                <a class="nav-link" href="#" onclick="showCart()">
                    <i class="fas fa-shopping-cart"></i>
                    <span id="cart-count" class="badge bg-light text-danger">0</span>
                </a>
            </div>
        </div>
    </div>
</nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
    <div class="overlay"></div> <!-- Tambahan -->
    <div class="container text-center">
        <h1 class="display-4 mb-4"> <strong> Kantin SMK Telkom Jakarta </strong> </h1>
        <p class="lead"><strong>nikmati makanan bergizi</strong> </p>
        <p><strong>pesan online, bayar, langsung ambil!</strong> </p>
        <a href="#menu" class="btn btn-light btn-lg">
            <i class="fas fa-utensils"></i> Lihat Menu Sekarang
        </a>
    </div>
</section><br><br><br>

    <!-- Menu Section -->
    <section id="cafetaria" class="py-5 bg-light">
    <div class="container">
        <h2 class="text-center mb-5 fw-bold">🍽 Menu Kantin SMK Telkom</h2>

        <?php foreach ($kantins as $kantin): ?>
            <div class="kantin-section mb-5 p-4 rounded-3 shadow-sm" style="background-color: #fff;">
                <div class="d-flex align-items-center mb-4">
                    <div class="kantin-icon me-3" style="font-size: 2rem;">
                        <?php if($kantin['id'] == 1): ?>
                            🏫
                        <?php else: ?> 
                            🏪
                        <?php endif; ?>
                    </div>
                    <h3 class="kantin-title m-0" style="font-size: 1.8rem; color: #2c3e50;">
                        <?= htmlspecialchars($kantin['nama_kantin']) ?>
                    </h3>
                </div>
                
                <div class="row g-4">
                    <?php 
                    $menu_items = getMenuByKantin($pdo, $kantin['id']);
                    foreach ($menu_items as $menu): 
                    ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card menu-card h-100 border-0 shadow-sm hover-effect">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title fw-bold mb-0"><?= htmlspecialchars($menu['nama_menu']) ?></h5>
                                    <span class="badge rounded-pill bg-<?= $menu['jenis'] == 'makanan' ? 'success' : 'primary' ?>">
                                        <?= $menu['jenis'] == 'makanan' ? '🍽' : '🥤' ?>
                                    </span>
                                </div>
                                
                                <div class="mt-auto">
                                    <p class="price-tag mb-2">
                                        <strong>Rp <?= number_format($menu['harga'], 0, ',', '.') ?></strong>
                                    </p>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="stock-indicator <?= $menu['stok'] > 0 ? 'text-success' : 'text-danger' ?>">
                                            <i class="fas fa-<?= $menu['stok'] > 0 ? 'check-circle' : 'times-circle' ?> me-1"></i>
                                            Stok: <?= $menu['stok'] ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($menu['stok'] > 0): ?>
                                    <button class="btn btn-add-cart w-100 py-2" 
                                            onclick="addToCart(<?= $menu['id'] ?>, '<?= htmlspecialchars($menu['nama_menu']) ?>', <?= $menu['harga'] ?>, '<?= htmlspecialchars($kantin['nama_kantin']) ?>')">
                                        <i class="fas fa-cart-plus me-2"></i>Tambah
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-outline-secondary w-100 py-2" disabled>
                                        <i class="fas fa-ban me-2"></i>Habis
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<style>
    .hover-effect {
        transition: all 0.3s ease;
        border-radius: 10px;
    }
    
    .hover-effect:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
    }
    
    .btn-add-cart {
        background-color: #4e73df;
        color: white;
        border-radius: 8px;
        transition: all 0.3s;
    }
    
    .btn-add-cart:hover {
        background-color: #2e59d9;
        transform: translateY(-2px);
    }
    
    .price-tag {
        font-size: 1.2rem;
        color: #2c3e50;
        padding: 5px 10px;
        background-color: #f8f9fa;
        border-radius: 6px;
        display: inline-block;
    }
    
    .stock-indicator {
        font-size: 0.9rem;
    }
    
    .kantin-header {
        position: relative;
        padding-bottom: 10px;
    }
    
    .kantin-header:after {
        content: "";
        position: absolute;
        bottom: 0;
        left: 0;
        width: 60px;
        height: 3px;
        background: linear-gradient(90deg, #4e73df, #2e59d9);
    }
</style>

<!-- About Kantin Section - Minimalist Version -->

<section id="about" class="py-5" style="padding-top: 80px;">
  <div class="container">
    <!-- Section Header -->
    <div class="text-center mb-5">
      <h2 class="text-danger fw-bold">Tentang Kantin SMK Telkom</h2>
      <div class="border-top border-danger w-25 mx-auto my-3"></div>
    </div>

    <!-- Horizontal Canteen Photo -->
    <div class="card shadow-lg mb-4 overflow-hidden">
      <img src="https://saintpauljember.sch.id/wp-content/uploads/2019/08/kantin.jpg" 
           class="img-fluid" 
           alt="Kantin SMK Telkom"
           style="height: 350px; object-fit: cover;">
      <div class="card-img-overlay d-flex align-items-end bg-dark bg-opacity-25">
        <h4 class="text-white mb-0 p-3">Kantin SMK Telkom Jakarta</h4>
      </div>
    </div>

    <div class="row">
      <!-- Left Column (Logo & Description) -->
      <div class="col-lg-4 mb-4">
        <!-- Logo Kantin -->
        <div class="text-center mb-4 p-3 bg-white rounded shadow-sm">
          <img src="https://smktelkom-pwt.sch.id/wp-content/uploads/2019/02/logo-telkom-schools-bundar-1024x1024.png" 
               alt="Logo Kantin" 
               class="img-fluid rounded-circle border border-4 border-danger mb-3"
               style="width: 150px;">
          <h4 class="text-danger">Kantin SMK Telkom</h4>
        </div>

        <!-- Video Kantin -->
         <div class="card shadow-sm mt-4">
          <div class="card-body p-0">
            <div class="ratio ratio-16x9">
              <iframe src="https://www.youtube.com/embed/i0rUVfRUEwM?si=lR4e7fMuo163cMZy" 
                      title="Virtual Tour Kantin SMK Telkom" 
                      frameborder="0" 
                      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                      allowfullscreen></iframe>
            </div>
            <div class="p-3 text-center">
              <h5 class="mb-1">Virtual Tour Kantin</h5>
              <small class="text-muted">Lihat fasilitas kantin kami</small>
            </div>
          </div>
        </div>
      </div>


      <!-- Right Column (Description) -->
      <div class="col-lg-8 mb-4">
        <div class="bg-white p-4 h-100 rounded shadow-sm">
          <h4 class="text-danger mb-3">About Kantin</h4>
          <p>Kantin SMK Telkom Jakarta merupakan fasilitas pendukung yang menyediakan berbagai kebutuhan makanan dan minuman untuk seluruh warga sekolah. 
            Berdiri sejak 2014 bersamaan dengan berdirinya SMK Telkom Jakarta di Daan Mogot. Sudah 11 tahun kami 
            berkerja sama dengan SMK Telkom Jakarta menyediakan makanan sehat, bergizi dan ramah di kantong pelajar.</p>
          
          <div class="row mt-4">
            <div class="col-md-6">
              <h5 class="text-danger"><i class="fas fa-utensils me-2"></i>Fasilitas</h5>
              <ul class="list-unstyled">
                <li class="mb-2"><i class="fas fa-check-circle text-danger me-2"></i> 20 Meja makan</li>
                <li class="mb-2"><i class="fas fa-check-circle text-danger me-2"></i> 10 Kios makanan</li>
                <li class="mb-2"><i class="fas fa-check-circle text-danger me-2"></i> Area bersih dan nyaman</li>
                <li class="mb-2"><i class="fas fa-check-circle text-danger me-2"></i> Menerima Sistem pembayaran digital</li>
              </ul>
            </div>
            <div class="col-md-6">
              <h5 class="text-danger"><i class="fas fa-clock me-2"></i>Jam Operasi</h5>
              <ul class="list-unstyled">
                <li class="mb-2"><strong>Senin-Jumat:</strong> 07.00 - 15.00</li>
              </ul>
            </div>
          </div>

          <div class="alert alert-danger mt-4">
            <h5><i class="fas fa-info-circle me-2"></i>Informasi Tambahan</h5>
            <p class="mb-0">Kantin kami memperkerjakan warga sekitar untuk menyediakan makanan sehat dengan harga terjangkau bagi siswa.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<style>
  .card-img-overlay h4 {
    text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
    background: linear-gradient(transparent, rgba(0,0,0,0.5));
    width: 100%;
  }
</style>
<section id="howtobuy" class="py-5 bg-white" style="padding-top: 80px;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 class="text-center text-danger mb-5">Cara Memesan Makanan</h2>
                
                <!-- Step-by-Step Guide -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="step">
                            <span class="step-number bg-danger text-white">1</span>
                            <h4 class="step-title">Pilih Kantin</h4>
                            <p>Pilih kantin yang tersedia dari daftar kantin kami</p>
                        </div>
                        <hr>
                        <div class="step">
                            <span class="step-number bg-danger text-white">2</span>
                            <h4 class="step-title">Pilih Menu</h4>
                            <p>Pilih makanan/minuman yang ingin dipesan</p>
                        </div>
                        <hr>
                        <div class="step">
                            <span class="step-number bg-danger text-white">3</span>
                            <h4 class="step-title">Atur Jumlah</h4>
                            <p>Masukkan jumlah pesanan yang diinginkan</p>
                        </div>
                        <hr>
                        <div class="step">
                            <span class="step-number bg-danger text-white">4</span>
                            <h4 class="step-title">Checkout</h4>
                            <p>Klik tombol checkout untuk menyelesaikan pemesanan</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>      
</section>

    <!-- Cart Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-shopping-cart"></i> Keranjang Belanja</h5>
                        </div>
                        <div class="card-body">
                            <div id="cart-items">
                                <p class="text-muted text-center">Keranjang masih kosong</p>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <strong>Total Pembayaran: </strong>
                                <strong id="cart-total">Rp 0</strong>
                            </div>
                            <button class="btn btn-success w-100 mt-3" id="checkout-btn" onclick="processOrder()" disabled>
                                <i class="fas fa-credit-card"></i> Proses Pembayaran
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">📞 Hubungi Kami</h2>
            
            <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?= $success_message ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?= $error_message ?></div>
            <?php endif; ?>
            
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="nama" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="nama" name="nama" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="pesan" class="form-label">Pesan / Saran</label>
                            <textarea class="form-control" id="pesan" name="pesan" rows="4" 
                                      placeholder="Tulis pesan, saran, atau kritik Anda..." required></textarea>
                        </div>
                        <button type="submit" name="submit_pesan" class="btn btn-primary">
                                                        <i class="fas fa-paper-plane"></i> Kirim Pesan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5>kantin SMK Telkom</h5>
                    <p>warung bu indah & warung mpok wati</p>
                </div>
                <div class="col-md-4 mb-3">
                    <h5>Jam Buka</h5>
                    <p>Senin - Jumat: 07:00 - 15:00</p>
                </div>
                <div class="col-md-4 mb-3">
                    <h5>Lokasi</h5>
                    <p>SMK Telkom Jakarta</p>
                </div>
            </div>
            <hr>
            <p>&copy; 2024 kantin SMK Telkom Jakarta</p>
        </div>
    </footer>

    <form id="order-form" method="POST" style="display: none;">
        <input type="hidden" name="process_order" value="1">
    </form>

    <!-- QR Modal -->
    <div class="modal fade" id="qrModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">💳 Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <h6>Total Pembayaran: <span id="payment-total"></span></h6>
                    <div class="qr-code">
                        <div>
                            <i class="fas fa-qrcode fa-3x"></i><br>
                            QR CODE PEMBAYARAN<br>
                            Tunjukkan ke kasir<br>
                            untuk pembayaran
                        </div>
                    </div>
                    <p class="text-muted">Scan QR code ini di kasir untuk menyelesaikan pembayaran</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="clearCart()">
                        Pembayaran berhasil
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($show_qr)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('payment-total').textContent = 'Rp <?= number_format($order_total, 0, ',', '.') ?>';
            new bootstrap.Modal(document.getElementById('qrModal')).show();
        });
    </script>
    <?php endif; ?>

    <!-- Hidden form -->
    <form id="order-form" method="POST" style="display: none;">
        <input type="hidden" name="process_order" value="1">
        <input type="hidden" name="cart_data" id="cart-data">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let cart = [];
        
        function addToCart(id, name, price, kantin) {
            const existingItem = cart.find(item => item.id === id);
            
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                cart.push({
                    id: id,
                    name: name,
                    price: price,
                    kantin: kantin,
                    quantity: 1
                });
            }
            
            updateCartDisplay();
            updateCartCount();
            alert(name + ' berhasil ditambahkan ke keranjang!');
        }
        
        function removeFromCart(id) {
            cart = cart.filter(item => item.id !== id);
            updateCartDisplay();
            updateCartCount();
        }
        
        function updateQuantity(id, quantity) {
            const item = cart.find(item => item.id === id);
            if (item) {
                item.quantity = parseInt(quantity);
                if (item.quantity <= 0) {
                    removeFromCart(id);
                } else {
                    updateCartDisplay();
                    updateCartCount();
                }
            }
        }
        
        function updateCartDisplay() {
            const cartItems = document.getElementById('cart-items');
            const cartTotal = document.getElementById('cart-total');
            const checkoutBtn = document.getElementById('checkout-btn');
            
            if (cart.length === 0) {
                cartItems.innerHTML = '<p class="text-muted text-center">Keranjang masih kosong</p>';
                cartTotal.textContent = 'Rp 0';
                checkoutBtn.disabled = true;
                return;
            }
            
            let total = 0;
            let html = '';
            
            cart.forEach(item => {
                const subtotal = item.price * item.quantity;
                total += subtotal;
                
                html += `
                    <div class="cart-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${item.name}</strong><br>
                                <small class="text-muted">📍 ${item.kantin}</small><br>
                                <small>💰 Rp ${item.price.toLocaleString()}</small>
                            </div>
                            <div class="d-flex align-items-center">
                                <input type="number" class="form-control form-control-sm" 
                                       style="width: 60px;" value="${item.quantity}" min="1"
                                       onchange="updateQuantity(${item.id}, this.value)">
                                <button class="btn btn-sm btn-danger ms-2" onclick="removeFromCart(${item.id})" title="Hapus item">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="text-end mt-2">
                            <strong>Subtotal: Rp ${subtotal.toLocaleString()}</strong>
                        </div>
                    </div>
                `;
            });
            
            cartItems.innerHTML = html;
            cartTotal.textContent = `Rp ${total.toLocaleString()}`;
            checkoutBtn.disabled = false;
        }
        
        function updateCartCount() {
            const count = cart.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('cart-count').textContent = count;
        }
        
        function showCart() {
            document.querySelector('.bg-light').scrollIntoView({ behavior: 'smooth' });
        }
        
        function processOrder() {
    if (cart.length === 0) return;
    
    if (confirm('Lanjutkan ke pembayaran?')) {
        const form = document.getElementById('order-form');
        const cartDataInput = document.getElementById('cart-data');
        
        // Serialize keranjang ke JSON dan isi hidden input
        cartDataInput.value = JSON.stringify(cart);
        
        // Submit form
        form.submit();
    }
}

        
        function clearCart() {
            cart = [];
            updateCartDisplay();
            updateCartCount();
            alert('Pembayaran anda berhasil! Silakan ambil pesanan Anda di konter. Terima kasih telah berbelanja!');
        }

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                console.log('🍽 Selamat datang di kantin SMK Telkom Jakarta!');
            }, 1000);
        });
    </script>
</body>
</html>