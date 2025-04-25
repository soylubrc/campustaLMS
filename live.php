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
function kisaltIlAdi($ilAdi, $uzunluk = 8) {
    if (strlen($ilAdi) > $uzunluk) {
        return substr($ilAdi, 0, $uzunluk) . '...';
    }
    return $ilAdi;
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
            font-size: 0.9rem;
        }
        .tur-card {
            margin-bottom: 15px;
            border: none;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.15);
        }
        .tur-header {
            font-size: 1.1rem;
            font-weight: bold;
        }
        .durak-item {
            padding: 6px 10px;
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
            font-size: 1.8rem;
            font-weight: bold;
            text-align: center;
            margin-bottom: 10px;
        }
        .tarih {
            font-size: 1.1rem;
            text-align: center;
            margin-bottom: 20px;
        }
        .baslik {
            text-align: center;
            margin-bottom: 20px;
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
            font-size: 0.8rem;
        }
        /* Paket durumu bildirimleri için stil */
        .paket-bildirim {
            padding: 4px 8px;
            margin-top: 5px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.8rem;
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
        .card-header {
            padding: 0.5rem 1rem;
        }
        .card-body {
            padding: 0.5rem;
        }
        .duraklar-container {
            display: flex;
            flex-wrap: wrap;
        }
        .durak-item {
            width: 50%;
            padding: 5px 8px;
            box-sizing: border-box;
        }
        .durak-info {
            display: flex;
            justify-content: space-between;
        }
        .durak-sira {
            font-weight: bold;
            margin-right: 5px;
        }
        .durak-detay {
            font-size: 0.8rem;
        }
    </style>
    <meta http-equiv="refresh" content="60">
</head>
<body>
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <h1 class="baslik">HAUS DES LOGISTICS TUR EKRANI</h1>
                <div class="saat" id="saat"></div>
                <div class="tarih" id="tarih"></div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0 fs-5">BUGÜNKÜ TURLAR</h3>
                    </div>
                    <div class="card-body p-2">
                        <?php 
                        $bugun_var = false;
                        ?>
                        
                        <div class="row g-2">
                            <?php
                            foreach ($turlar as $tur):
                                if (date('Y-m-d', strtotime($tur['cikis_tarihi'])) == date('Y-m-d')):
                                    $bugun_var = true;
                            ?>
                                <div class="col-md-6 mb-2">
                                    <div class="card tur-card h-100">
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
                                            <div class="p-2">
                                                <p class="mb-1"><strong>Şoför:</strong> <?= $tur['sofor_adi'] ?? 'Atanmadı' ?></p>
                                                
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
                                                <div class="duraklar-container">
                                                    <?php foreach ($duraklar[$tur['tur_id']] as $durak): ?>
                                                        <div class="durak-item <?= isset($durak['teslim_durumu']) && $durak['teslim_durumu'] == 'Teslim Edildi' ? 'teslim-edildi' : 'bekleniyor' ?>">
                                                            <div class="durak-info">
                                                                <div>
                                                                    <span class="durak-sira"><?= $durak['sira'] ?? '#' ?>.</span>
                                                                   <?= kisaltIlAdi($durak['il_adi'] ?? 'Bilinmeyen') ?>
                                                                </div>
                                                                <span class="badge <?= isset($durak['teslim_durumu']) && $durak['teslim_durumu'] == 'Teslim Edildi' ? 'bg-success' : 'bg-warning' ?>">
                                                                    <?= $durak['teslim_durumu'] ?? 'Beklemede' ?>
                                                                </span>
                                                            </div>
                                                            <!--<div class="durak-detay">
                                                                <span class="<?= isset($durak['yuk_tipi']) && $durak['yuk_tipi'] == 'İçecek' ? 'yuk-tipi-icecek' : 'yuk-tipi-normal' ?>">
                                                                    <?= $durak['yuk_tipi'] ?? 'Belirtilmemiş' ?>
                                                                </span>
                                                                - <?= $durak['yuk_miktari'] ?? '0' ?> kg
                                                            </div>-->
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="p-2 text-center text-muted">
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
                        <h3 class="mb-0 fs-5">YARININ TURLARI</h3>
                    </div>
                    <div class="card-body p-2">
                        <div class="row g-2">
                            <?php 
                            $yarin_var = false;
                            foreach ($turlar as $tur):
                                if (date('Y-m-d', strtotime($tur['cikis_tarihi'])) == date('Y-m-d', strtotime('+1 day'))):
                                    $yarin_var = true;
                            ?>
                                <div class="col-md-6 mb-2">
                                    <div class="card tur-card h-100">
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
                                            <div class="p-2">
                                                <p class="mb-1"><strong>Şoför:</strong> <?= $tur['sofor_adi'] ?? 'Atanmadı' ?></p>
                                                
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
                                                <div class="duraklar-container">
                                                    <?php foreach ($duraklar[$tur['tur_id']] as $durak): ?>
                                                        <div class="durak-item bekleniyor">
                                                            <div class="durak-info">
                                                                <div>
                                                                    <span class="durak-sira"><?= $durak['sira'] ?? '#' ?>.</span>
                                                                    <?= $durak['il_adi'] ?? 'Bilinmeyen' ?>
                                                                </div>
                                                                <span class="badge bg-secondary">
                                                                    Planlandı
                                                                </span>
                                                            </div>
                                                            <div class="durak-detay">
                                                                <span class="<?= isset($durak['yuk_tipi']) && $durak['yuk_tipi'] == 'İçecek' ? 'yuk-tipi-icecek' : 'yuk-tipi-normal' ?>">
                                                                    <?= $durak['yuk_tipi'] ?? 'Belirtilmemiş' ?>
                                                                </span>
                                                                - <?= $durak['yuk_miktari'] ?? '0' ?> kg
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="p-2 text-center text-muted">
                                                    Durak bilgisi bulunamadı.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php
                                endif;
                            endforeach;
                            
                            if (!$yarin_var):
                            ?>
                                <div class="col-12">
                                    <div class="alert alert-info text-center">
                                        Yarın için planlanmış tur bulunmamaktadır.
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="refresh-info">
            <p>Bu sayfa her 60 saniyede bir otomatik olarak yenilenir. Son güncelleme: <span id="son-guncelleme"></span></p>
            <a href="index.php" class="btn btn-primary btn-sm">Yönetim Paneline Dön</a>
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
