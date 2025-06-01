<?php
// config.php
$host = 'localhost';
$dbname = 'kantin_smk';
$username = 'root';  // sesuaikan dengan username database Anda
$password = '';      // sesuaikan dengan password database Anda

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

function getKantin($pdo) {
    $stmt = $pdo->query("SELECT * FROM kantin");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMenuByKantin($pdo, $kantin_id) {
    $stmt = $pdo->prepare("SELECT * FROM menu WHERE kantin_id = ?");
    $stmt->execute([$kantin_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function simpanPesan($pdo, $nama, $email, $pesan) {
    try {
        $stmt = $pdo->prepare("INSERT INTO pesan (nama, email, pesan) VALUES (?, ?, ?)");
        return $stmt->execute([$nama, $email, $pesan]);
    } catch(PDOException $e) {
        return false;
    }
}
function updateStok($pdo, $menu_id, $quantity) {
    try {
        $stmt = $pdo->prepare("UPDATE menu SET stok = stok - ? WHERE id = ? AND stok >= ?");
        return $stmt->execute([$quantity, $menu_id, $quantity]);
    } catch(PDOException $e) {
        return false;
    }
}
?>