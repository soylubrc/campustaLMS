<?php
require_once 'config.php';

// Bugün ve yarın için planlanan turları getir
$stmt = $db->query("SELECT t.*,
                     CONCAT(s.ad, ' ', s.soyad) as sofor_adi,
                     a.plaka
                     FROM turlar t
                    LEFT JOIN soforler s ON t.sofor_id = s.sofor_id
                    LEFT JOIN araclar a ON t.arac_id = a.arac_id
                    WHERE t.durum IN ('Planlandı', 'Yolda') 
                     AND (t.cikis_tarihi = CURDATE() OR t.cikis_tarihi = DATE_ADD(CURDATE(), INTERVAL 1 DAY))
                    ORDER BY t.cikis_tarihi, t.cikis_saati");
$turlar = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Durakları getir
$duraklar = [];
if (count($turlar) > 0) {
    $tur_idler = array_column($turlar, 'tur_id');
    $tur_id_str = implode(',', $tur_idler);
    
    // SQL sorgusunda tüm gerekli alanların seçildiğinden emin olun
    $stmt = $db->query("SELECT td.*, t.tur_id, i.il_adi, yt.tip_adi as yuk_tipi
                        FROM tur_duraklar td
                        JOIN turlar t ON td.tur_id = t.tur_id
                        LEFT JOIN iller i ON td.il_id = i.il_id
                        LEFT JOIN yuk_tipleri yt ON td.yuk_tip_id = yt.yuk_tip_id
                        WHERE td.tur_id IN ($tur_id_str)
                        ORDER BY td.tur_id, td.sira");
    
    $tum_duraklar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tum_duraklar as $durak) {
        $duraklar[$durak['tur_id']][] = $durak;
    }
}

// Yardımcı fonksiyon - Eğer tanımlanmamışsa
if (!function_exists('formatSaat')) {
    function formatSaat($saat) {
        return date('H:i', strtotime($saat));
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canlı Tur Ekranı - Haus des Logistics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .tur-card {
            margin-bottom: 20px;
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .tur-header {
            font-size: 1.2rem;
            font-weight: bold;
        }
        .durak-item {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        .durak-item:last-child {
            border-bottom: none;
        }
        .durak-item.teslim-edildi {
            background-color: #d1e7dd;
        }
        .durak-item.bekleniyor {
            background-color: #fff3cd;
        }
        .saat {
            font-size: 2rem;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
        }
        .tarih {
            font-size: 1.2rem;
            text-align: center;
            margin-bottom: 30px;
        }
        .baslik {
            text-align: center;
            margin-bottom: 30px;
            color: #0d6efd;
        }
        .yuk-tipi-icecek {
            color: #0d6efd;
            font-weight: bold;
        }
        .yuk-tipi-normal {
            color: #198754;
            font-weight: bold;
        }
        .refresh-info {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
        }
        /* Paket durumu bildirimleri için stil */
        .paket-bildirim {
            padding: 8px 12px;
            margin-top: 10px;
            border-radius: 4px;
            font-weight: bold;
        }
        .paket-hazir {
            background-color: #3ae309;
            color: #fff;
        }
        .paket-hazirlaniyor {
            background-color: #ff9896;
            color: #fff;
        }
        .paket-beklemede {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
    <meta http-equiv="refresh" content="60">
</head>
<body>
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="baslik">HAUS DES LOGISTICS TUR EKRANI</h1>
                <div class="saat" id="saat"></div>
                <div class="tarih" id="tarih"></div>
            </div>
        </div>
        
       <div class="row">
    <div class="col-md-6">
        <div class="card mb-6">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">BUGÜNKÜ TURLAR</h3>
            </div>
            <div class="card-body">
                <?php 
                $bugun_var = false;
                ?>
                
                <!-- Bugünkü turlar için row ekliyoruz -->
                <div class="row">
                    <?php
                    foreach ($turlar as $tur):
                        if (date('Y-m-d', strtotime($tur['cikis_tarihi'])) == date('Y-m-d')):
                            $bugun_var = true;
                    ?>
                        <!-- Her tur için col-md-6 kullanarak yan yana iki tur gösteriyoruz -->
                        <div class="col-md-12 mb-6">
                            <div class="card tur-card">
                                <div class="card-header <?= $tur['durum'] == 'Yolda' ? 'bg-warning' : 'bg-info' ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="tur-header">
                                            <?= formatSaat($tur['cikis_saati']) ?> - <?= $tur['plaka'] ?? 'Plaka Yok' ?>
                                        </span>
                                        <span class="badge <?= $tur['durum'] == 'Yolda' ? 'bg-danger' : 'bg-primary' ?>">
                                            <?= $tur['durum'] ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="p-3">
                                        <p class="mb-1"><strong>Şoför:</strong> <?= $tur['sofor_adi'] ?? 'Atanmadı' ?></p>
                                        <p class="mb-0"><strong>Toplam Mesafe:</strong> <?= $tur['toplam_mesafe'] ?? '0' ?> km</p>
                                        
                                        <?php if (isset($tur['paket_durumu']) && !empty($tur['paket_durumu'])): ?>
                                            <div class="paket-bildirim <?= 
                                                $tur['paket_durumu'] == 'Hazır' ? 'paket-hazir' : 
                                                ($tur['paket_durumu'] == 'Hazırlanıyor' ? 'paket-hazirlaniyor' : 'paket-beklemede') 
                                            ?>">
                                                Paket Durumu: <?= $tur['paket_durumu'] ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (isset($duraklar[$tur['tur_id']])): ?>
                                        <div class="duraklar">
                                            <?php foreach ($duraklar[$tur['tur_id']] as $durak): ?>
                                                <div class="durak-item <?= isset($durak['teslim_durumu']) && $durak['teslim_durumu'] == 'Teslim Edildi' ? 'teslim-edildi' : 'bekleniyor' ?>">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span><strong><?= $durak['sira'] ?? '#' ?>.</strong> <?= $durak['il_adi'] ?? 'Bilinmeyen Konum' ?></span>
                                                        <span class="badge <?= isset($durak['teslim_durumu']) && $durak['teslim_durumu'] == 'Teslim Edildi' ? 'bg-success' : 'bg-warning' ?>">
                                                            <?= $durak['teslim_durumu'] ?? 'Beklemede' ?>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <span class="<?= isset($durak['yuk_tipi']) && $durak['yuk_tipi'] == 'İçecek' ? 'yuk-tipi-icecek' : 'yuk-tipi-normal' ?>">
                                                            <?= $durak['yuk_tipi'] ?? 'Belirtilmemiş' ?>
                                                        </span>
                                                        - <?= $durak['yuk_miktari'] ?? '0' ?> kg
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="p-3 text-center text-muted">
                                            Durak bilgisi bulunamadı.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php
                        endif;
                    endforeach;
                    
                    if (!$bugun_var):
                    ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                Bugün için planlanmış tur bulunmamaktadır.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h3 class="mb-0">YARININ TURLARI</h3>
                    </div>
                    <div class="card-body">
                        <?php 
                        $yarin_var = false;
                        foreach ($turlar as $tur):
                            if (date('Y-m-d', strtotime($tur['cikis_tarihi'])) == date('Y-m-d', strtotime('+1 day'))):
                                $yarin_var = true;
                        ?>
                            <div class="card tur-card">
                                <div class="card-header bg-info">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="tur-header">
                                            <?= formatSaat($tur['cikis_saati']) ?> - <?= $tur['plaka'] ?? 'Plaka Yok' ?>
                                        </span>
                                        <span class="badge bg-primary">
                                            <?= $tur['durum'] ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="p-3">
                                        <p class="mb-1"><strong>Şoför:</strong> <?= $tur['sofor_adi'] ?? 'Atanmadı' ?></p>
                                        <p class="mb-0"><strong>Toplam Mesafe:</strong> <?= $tur['toplam_mesafe'] ?? '0' ?> km</p>
                                        
                                        <?php if (isset($tur['paket_durumu']) && !empty($tur['paket_durumu'])): ?>
                                            <div class="paket-bildirim <?= 
                                                $tur['paket_durumu'] == 'Hazır' ? 'paket-hazir' : 
                                                ($tur['paket_durumu'] == 'Hazırlanıyor' ? 'paket-hazirlaniyor' : 'paket-beklemede') 
                                            ?>">
                                                Paket Durumu: <?= $tur['paket_durumu'] ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (isset($duraklar[$tur['tur_id']])): ?>
                                        <div class="duraklar">
                                            <?php foreach ($duraklar[$tur['tur_id']] as $durak): ?>
                                                <div class="durak-item bekleniyor">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span><strong><?= $durak['sira'] ?? '#' ?>.</strong> <?= $durak['il_adi'] ?? 'Bilinmeyen Konum' ?></span>
                                                        <span class="badge bg-secondary">
                                                            Planlandı
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <span class="<?= isset($durak['yuk_tipi']) && $durak['yuk_tipi'] == 'İçecek' ? 'yuk-tipi-icecek' : 'yuk-tipi-normal' ?>">
                                                            <?= $durak['yuk_tipi'] ?? 'Belirtilmemiş' ?>
                                                        </span>
                                                        - <?= $durak['yuk_miktari'] ?? '0' ?> kg
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="p-3 text-center text-muted">
                                            Durak bilgisi bulunamadı.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php
                            endif;
                        endforeach;
                        
                        if (!$yarin_var):
                        ?>
                            <div class="alert alert-info text-center">
                                Yarın için planlanmış tur bulunmamaktadır.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="refresh-info">
            <p>Bu sayfa her 60 saniyede bir otomatik olarak yenilenir. Son güncelleme: <span id="son-guncelleme"></span></p>
            <a href="index.php" class="btn btn-primary">Yönetim Paneline Dön</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Saat ve tarih güncelleme
        function guncelleZaman() {
            const simdi = new Date();
            
            // Saat
            const saat = simdi.getHours().toString().padStart(2, '0');
            const dakika = simdi.getMinutes().toString().padStart(2, '0');
            const saniye = simdi.getSeconds().toString().padStart(2, '0');
            document.getElementById('saat').textContent = `${saat}:${dakika}:${saniye}`;
            
            // Tarih
            const gun = simdi.getDate().toString().padStart(2, '0');
            const ay = (simdi.getMonth() + 1).toString().padStart(2, '0');
            const yil = simdi.getFullYear();
            
            const gunler = ["Pazar", "Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi"];
            const gunAdi = gunler[simdi.getDay()];
            
            document.getElementById('tarih').textContent = `${gun}.${ay}.${yil} ${gunAdi}`;
            
            // Son güncelleme
            document.getElementById('son-guncelleme').textContent = `${saat}:${dakika}:${saniye}`;
        }
        
        // Sayfa yüklendiğinde ve her saniye çalıştır
        guncelleZaman();
        setInterval(guncelleZaman, 1000);
    </script>
</body>
</html>