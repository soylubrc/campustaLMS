<?php
require_once 'config.php';

// Şoförleri getir
$stmt = $db->query("SELECT sofor_id, CONCAT(ad, ' ', soyad) AS tam_ad FROM soforler ORDER BY ad, soyad");
$soforler = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Rapor türleri
$rapor_turleri = [
    'hafta_sonu_mesai' => 'Hafta Sonu Mesai Raporu',
    'km_raporu' => 'Kilometre Raporu',
    'tur_paket_durum' => 'Tur Paketi Durum Raporu'
];

$rapor_sonuclari = [];
$rapor_turu = isset($_GET['rapor_turu']) ? $_GET['rapor_turu'] : '';
$baslangic_tarihi = isset($_GET['baslangic_tarihi']) ? $_GET['baslangic_tarihi'] : '';
$bitis_tarihi = isset($_GET['bitis_tarihi']) ? $_GET['bitis_tarihi'] : '';
$sofor_id = isset($_GET['sofor_id']) ? $_GET['sofor_id'] : '';
$ay = isset($_GET['ay']) ? $_GET['ay'] : date('m');
$yil = isset($_GET['yil']) ? $_GET['yil'] : date('Y');
$paket_durumu = isset($_GET['paket_durumu']) ? $_GET['paket_durumu'] : '';

// Rapor oluşturma
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($rapor_turu)) {
    
    // Hafta sonu mesai raporu
    if ($rapor_turu === 'hafta_sonu_mesai') {
        $query = "
            SELECT 
                s.sofor_id,
                s.ad,
                s.soyad,
                COUNT(DISTINCT CASE WHEN DAYOFWEEK(t.cikis_tarihi) = 1 THEN t.tur_id END) AS pazar_sayisi,
                COUNT(DISTINCT CASE WHEN DAYOFWEEK(t.cikis_tarihi) = 7 THEN t.tur_id END) AS cumartesi_sayisi,
                COUNT(DISTINCT CASE WHEN DAYOFWEEK(t.cikis_tarihi) IN (1, 7) THEN t.tur_id END) AS toplam_hafta_sonu
            FROM 
                soforler s
            LEFT JOIN 
                turlar t ON s.sofor_id = t.sofor_id
            WHERE 
                MONTH(t.cikis_tarihi) = ? AND YEAR(t.cikis_tarihi) = ?
        ";
        
        $params = [$ay, $yil];
        
        if (!empty($sofor_id)) {
            $query .= " AND s.sofor_id = ?";
            $params[] = $sofor_id;
        }
        
        $query .= " GROUP BY s.sofor_id ORDER BY s.ad, s.soyad";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $rapor_sonuclari = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Kilometre raporu
    else if ($rapor_turu === 'km_raporu') {
        if (empty($baslangic_tarihi) || empty($bitis_tarihi)) {
            $hata = "Başlangıç ve bitiş tarihi seçmelisiniz.";
        } else {
            $query = "
                SELECT 
                    s.sofor_id,
                    s.ad,
                    s.soyad,
                    COUNT(t.tur_id) AS tur_sayisi,
                    SUM(t.toplam_mesafe) AS toplam_km
                FROM 
                    soforler s
                LEFT JOIN 
                    turlar t ON s.sofor_id = t.sofor_id
                WHERE 
                    t.cikis_tarihi BETWEEN ? AND ?
            ";
            
            $params = [$baslangic_tarihi, $bitis_tarihi];
            
            if (!empty($sofor_id)) {
                $query .= " AND s.sofor_id = ?";
                $params[] = $sofor_id;
            }
            
            $query .= " GROUP BY s.sofor_id ORDER BY toplam_km DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $rapor_sonuclari = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // Tur paketi durum raporu
    else if ($rapor_turu === 'tur_paket_durum') {
        $query = "
            SELECT 
                t.tur_id,
                t.cikis_tarihi,
                t.cikis_saati,
                t.paket_durumu,
                s.ad AS sofor_adi,
                s.soyad AS sofor_soyadi,
                a.plaka
            FROM 
                turlar t
            LEFT JOIN 
                soforler s ON t.sofor_id = s.sofor_id
            LEFT JOIN 
                araclar a ON t.arac_id = a.arac_id
            WHERE 
                1=1
        ";
        
        $params = [];
        
        if (!empty($baslangic_tarihi)) {
            $query .= " AND t.cikis_tarihi >= ?";
            $params[] = $baslangic_tarihi;
        }
        
        if (!empty($bitis_tarihi)) {
            $query .= " AND t.cikis_tarihi <= ?";
            $params[] = $bitis_tarihi;
        }
        
        if (!empty($sofor_id)) {
            $query .= " AND t.sofor_id = ?";
            $params[] = $sofor_id;
        }
        
        if (!empty($paket_durumu)) {
            $query .= " AND t.paket_durumu = ?";
            $params[] = $paket_durumu;
        }
        
        $query .= " ORDER BY t.cikis_tarihi DESC, t.cikis_saati DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $rapor_sonuclari = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raporlar - Lojistik Firma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Raporlar</h1>
            <a href="index.php" class="btn btn-primary">Ana Sayfa</a>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Rapor Filtresi</h5>
            </div>
            <div class="card-body">
                <form method="get" action="" id="rapor-form">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="rapor_turu" class="form-label">Rapor Türü</label>
                            <select class="form-select" id="rapor_turu" name="rapor_turu" required>
                                <option value="">Seçiniz</option>
                                <?php foreach ($rapor_turleri as $key => $value): ?>
                                    <option value="<?= $key ?>" <?= $rapor_turu === $key ? 'selected' : '' ?>><?= $value ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="sofor_id" class="form-label">Şoför</label>
                            <select class="form-select" id="sofor_id" name="sofor_id">
                                <option value="">Tüm Şoförler</option>
                                <?php foreach ($soforler as $sofor): ?>
                                    <option value="<?= $sofor['sofor_id'] ?>" <?= $sofor_id == $sofor['sofor_id'] ? 'selected' : '' ?>><?= htmlspecialchars($sofor['tam_ad']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
					  <?php 
					  setlocale(LC_TIME, 'tr_TR');
					  date_default_timezone_set('Europe/Istanbul');
					  ?>
<!-- Hafta Sonu Mesai Raporu için Ay/Yıl Seçimi -->
<div class="row mb-3" id="hafta-sonu-filtre" style="display: <?= $rapor_turu === 'hafta_sonu_mesai' ? 'flex' : 'none' ?>;">
    <div class="col-md-3">
        <label for="ay" class="form-label">Ay</label>
        <select class="form-select" id="ay" name="ay">
            <?php 
            // Türkçe ay adları
            $aylar = [
                '01' => 'Ocak',
                '02' => 'Şubat',
                '03' => 'Mart',
                '04' => 'Nisan',
                '05' => 'Mayıs',
                '06' => 'Haziran',
                '07' => 'Temmuz',
                '08' => 'Ağustos',
                '09' => 'Eylül',
                '10' => 'Ekim',
                '11' => 'Kasım',
                '12' => 'Aralık'
            ];
            
            foreach ($aylar as $ay_no => $ay_adi): 
            ?>
                <option value="<?= $ay_no ?>" <?= $ay == $ay_no ? 'selected' : '' ?>><?= $ay_adi ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label for="yil" class="form-label">Yıl</label>
        <select class="form-select" id="yil" name="yil">
            <?php for ($i = date('Y') - 5; $i <= date('Y') + 1; $i++): ?>
                <option value="<?= $i ?>" <?= $yil == $i ? 'selected' : '' ?>><?= $i ?></option>
            <?php endfor; ?>
        </select>
    </div>
</div>

                    
                    <!-- Kilometre Raporu için Tarih Aralığı -->
                    <div class="row mb-3" id="km-filtre" style="display: <?= $rapor_turu === 'km_raporu' ? 'flex' : 'none' ?>;">
                        <div class="col-md-3">
                            <label for="baslangic_tarihi" class="form-label">Başlangıç Tarihi</label>
                            <input type="date" class="form-control" id="baslangic_tarihi" name="baslangic_tarihi" value="<?= $baslangic_tarihi ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="bitis_tarihi" class="form-label">Bitiş Tarihi</label>
                            <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi" value="<?= $bitis_tarihi ?>">
                        </div>
                    </div>
                    
                    <!-- Tur Paketi Durum Raporu için Filtreler -->
                    <div class="row mb-3" id="paket-durum-filtre" style="display: <?= $rapor_turu === 'tur_paket_durum' ? 'flex' : 'none' ?>;">
                        <div class="col-md-3">
                            <label for="baslangic_tarihi" class="form-label">Başlangıç Tarihi</label>
                            <input type="date" class="form-control" id="baslangic_tarihi" name="baslangic_tarihi" value="<?= $baslangic_tarihi ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="bitis_tarihi" class="form-label">Bitiş Tarihi</label>
                            <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi" value="<?= $bitis_tarihi ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="paket_durumu" class="form-label">Paket Durumu</label>
                            <select class="form-select" id="paket_durumu" name="paket_durumu">
                                <option value="">Tümü</option>
                                <option value="Hazırlanıyor" <?= $paket_durumu === 'Hazırlanıyor' ? 'selected' : '' ?>>Hazırlanıyor</option>
                                <option value="Hazır" <?= $paket_durumu === 'Hazır' ? 'selected' : '' ?>>Hazır</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Raporu Oluştur</button>
                </form>
            </div>
        </div>
        
        <?php if (!empty($rapor_turu) && !empty($rapor_sonuclari)): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>
                        <?= $rapor_turleri[$rapor_turu] ?> 
                        <?php if ($rapor_turu === 'hafta_sonu_mesai'): ?>
                            (<?= date('F Y', mktime(0, 0, 0, $ay, 1, $yil)) ?>)
                        <?php elseif ($rapor_turu === 'km_raporu' && !empty($baslangic_tarihi) && !empty($bitis_tarihi)): ?>
                            (<?= date('d.m.Y', strtotime($baslangic_tarihi)) ?> - <?= date('d.m.Y', strtotime($bitis_tarihi)) ?>)
                        <?php endif; ?>
                    </h5>
                    <button class="btn btn-success" onclick="exportTableToExcel('rapor-tablo', '<?= $rapor_turleri[$rapor_turu] ?>')">Excel'e Aktar</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered" id="rapor-tablo">
                            <?php if ($rapor_turu === 'hafta_sonu_mesai'): ?>
                                <thead>
                                    <tr>
                                        <th>Şoför</th>
                                        <th>Cumartesi Mesai Sayısı</th>
                                        <th>Pazar Mesai Sayısı</th>
                                        <th>Toplam Hafta Sonu Mesai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rapor_sonuclari as $sonuc): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($sonuc['ad'] . ' ' . $sonuc['soyad']) ?></td>
                                            <td><?= $sonuc['cumartesi_sayisi'] ?: 0 ?></td>
                                            <td><?= $sonuc['pazar_sayisi'] ?: 0 ?></td>
                                            <td><?= $sonuc['toplam_hafta_sonu'] ?: 0 ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            <?php elseif ($rapor_turu === 'km_raporu'): ?>
                                <thead>
                                    <tr>
                                        <th>Şoför</th>
                                        <th>Tur Sayısı</th>
                                        <th>Toplam Kilometre</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rapor_sonuclari as $sonuc): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($sonuc['ad'] . ' ' . $sonuc['soyad']) ?></td>
                                            <td><?= $sonuc['tur_sayisi'] ?: 0 ?></td>
                                            <td><?= $sonuc['toplam_km'] ? number_format($sonuc['toplam_km'], 0, ',', '.') . ' km' : '0 km' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            <?php elseif ($rapor_turu === 'tur_paket_durum'): ?>
                                <thead>
                                    <tr>
                                        <th>Tur ID</th>
                                        <th>Çıkış Tarihi</th>
                                        <th>Şoför</th>
                                        <th>Araç Plakası</th>
                                        <th>Paket Durumu</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rapor_sonuclari as $sonuc): ?>
                                        <tr>
                                            <td><?= $sonuc['tur_id'] ?></td>
                                            <td><?= date('d.m.Y H:i', strtotime($sonuc['cikis_tarihi'] . ' ' . $sonuc['cikis_saati'])) ?></td>
                                            <td><?= htmlspecialchars($sonuc['sofor_adi'] . ' ' . $sonuc['sofor_soyadi']) ?></td>
                                            <td><?= htmlspecialchars($sonuc['plaka']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $sonuc['paket_durumu'] === 'Hazır' ? 'success' : 'warning' ?>">
                                                    <?= $sonuc['paket_durumu'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="tur_paket_durum.php?id=<?= $sonuc['tur_id'] ?>" class="btn btn-sm btn-primary">Durumu Güncelle</a>
                                                <a href="tur_detay.php?id=<?= $sonuc['tur_id'] ?>" class="btn btn-sm btn-info">Detay</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        <?php elseif (!empty($rapor_turu) && empty($rapor_sonuclari)): ?>
            <div class="alert alert-info">Seçilen kriterlere uygun rapor verisi bulunamadı.</div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Rapor türüne göre filtreleri göster/gizle
        document.getElementById('rapor_turu').addEventListener('change', function() {
            const raporTuru = this.value;
            
            document.getElementById('hafta-sonu-filtre').style.display = raporTuru === 'hafta_sonu_mesai' ? 'flex' : 'none';
            document.getElementById('km-filtre').style.display = raporTuru === 'km_raporu' ? 'flex' : 'none';
            document.getElementById('paket-durum-filtre').style.display = raporTuru === 'tur_paket_durum' ? 'flex' : 'none';
        });
        
        // Excel'e aktarma fonksiyonu
        function exportTableToExcel(tableID, filename = '') {
            const table = document.getElementById(tableID);
            const wb = XLSX.utils.table_to_book(table, {sheet: "Rapor"});
            XLSX.writeFile(wb, (filename ? filename : 'Rapor') + '.xlsx');
        }
    </script>
    
    <!-- Excel export için SheetJS kütüphanesi -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
</body>
</html>