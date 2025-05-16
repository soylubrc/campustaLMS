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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* Genel Stiller */
    .dashboard-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .dashboard-title {
        font-size: 24px;
        font-weight: 600;
        color: #333;
        display: flex;
        align-items: center;
    }
    
    .dashboard-title i {
        margin-right: 10px;
        color: #4a6cf7;
    }
    
    .dashboard-stats {
        display: flex;
        gap: 15px;
    }
    
    .stat-box {
        background: #fff;
        border-radius: 8px;
        padding: 12px 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        min-width: 120px;
        text-align: center;
    }
    
    .stat-number {
        font-size: 22px;
        font-weight: bold;
        margin-bottom: 5px;
    }
    
    .stat-label {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
    }
    
    /* Turlar Tablosu */
    .tours-container {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        overflow: hidden;
        margin-bottom: 30px;
    }
    
    .tours-header {
        background: #f8f9fa;
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
    }
    
    .tours-title {
        font-size: 18px;
        font-weight: 600;
        color: #333;
        margin: 0;
        display: flex;
        align-items: center;
    }
    
    .tours-title i {
        margin-right: 10px;
        color: #4a6cf7;
    }
    
    .tours-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
        padding: 20px;
    }
    
    .tour-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        overflow: hidden;
        transition: all 0.3s ease;
        border: 1px solid #eaeaea;
        position: relative;
    }
    
    .tour-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0,0,0,0.1);
    }
    
    .tour-status-indicator {
        position: absolute;
        top: 0;
        left: 0;
        width: 5px;
        height: 100%;
    }
    
    .status-waiting {
        background-color: #ffc107;
    }
    
    .status-delivered {
        background-color: #28a745;
    }
    
    .status-error {
        background-color: #dc3545;
    }
    
    .tour-header {
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
        position: relative;
    }
    
    .tour-route {
        display: flex;
        align-items: center;
        font-weight: 600;
        font-size: 15px;
        margin-bottom: 8px;
    }
    
    .tour-city {
        max-width: 120px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .tour-arrow {
        margin: 0 10px;
        color: #999;
        font-size: 14px;
    }
    
    .tour-status {
        position: absolute;
        top: 15px;
        right: 15px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        display: flex;
        align-items: center;
    }
    
    .tour-status i {
        margin-right: 5px;
        font-size: 11px;
    }
    
    .status-badge-waiting {
        background-color: #fff8e1;
        color: #f57c00;
    }
    
    .status-badge-delivered {
        background-color: #e8f5e9;
        color: #2e7d32;
    }
    
    .status-badge-error {
        background-color: #ffebee;
        color: #c62828;
    }
    
    .tour-body {
        padding: 15px;
    }
    
    .tour-info-row {
        display: flex;
        margin-bottom: 10px;
        align-items: center;
    }
    
    .tour-info-row:last-child {
        margin-bottom: 0;
    }
    
    .tour-info-icon {
        width: 30px;
        color: #6c757d;
        font-size: 14px;
    }
    
    .tour-info-content {
        flex: 1;
        font-size: 14px;
    }
    
    .tour-info-label {
        font-weight: 500;
        margin-right: 5px;
        color: #555;
    }
    
    .tour-footer {
        background: #f9f9f9;
        padding: 10px 15px;
        font-size: 12px;
        color: #777;
        border-top: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
    }
    
    /* Tamamlanmış Tur Kartı */
    .tour-completed {
        background-color: #f8f9fa;
        opacity: 0.85;
    }
    
    .tour-completed-badge {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-15deg);
        background: rgba(40, 167, 69, 0.9);
        color: white;
        padding: 5px 15px;
        border-radius: 5px;
        font-weight: bold;
        z-index: 10;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }
    
    /* Boş Durum */
    .no-tours {
        text-align: center;
        padding: 40px 20px;
        color: #6c757d;
    }
    
    .no-tours i {
        font-size: 40px;
        margin-bottom: 15px;
        opacity: 0.5;
    }
    
    /* Responsive Ayarlar */
    @media (max-width: 768px) {
        .dashboard-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .dashboard-stats {
            margin-top: 15px;
            width: 100%;
            overflow-x: auto;
            padding-bottom: 10px;
        }
        
        .tours-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

    <meta http-equiv="refresh" content="60">
</head>
<body>
<?php
// Mevcut PHP kodları aynen kalacak
?>

<!-- Bugünün Turları Yeni Tasarım -->
<div class="dashboard-container">
<!-- Sayfa Header Bölümü -->
<div class="page-header" style="background: linear-gradient(135deg, #4a6cf7, #2541b2); color: white; padding: 20px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <!-- Logo ve Başlık Alanı -->
        <div style="display: flex; align-items: center;">
            <div class="logo" style="margin-right: 15px;">
                <!-- Logo (Font Awesome ile) -->
                <img src="https://hausdeslogistics.de/wp-content/uploads/2024/02/logowebp.webp" width="120px">
            </div>
            <div class="title-area">
                <h1 style="margin: 0; font-size: 28px; font-weight: 600;">Haus des Logistics</h1>
                <div style="font-size: 16px; opacity: 0.9;">Tur Yönetim Sistemi</div>
            </div>
        </div>
        
        <!-- Tarih ve Saat Alanı -->
        <div class="date-time" style="text-align: right;">
            <div class="current-date" style="font-size: 32px; font-weight: 600;">
                <?php
                // Türkçe ay adları
                $aylar = [
                    1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
                    5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
                    9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'
                ];
                
                $gun = date('j');
                $ay = $aylar[date('n')];
                $yil = date('Y');
                
                echo "$gun $ay $yil";
                ?>
            </div>
            <div class="current-time" style="font-size: 24px; margin-top: 5px;" id="current-time">
                <?= date('H:i:s') ?>
            </div>
        </div>
    </div>
    
    <!-- Alt Bilgi Çubuğu -->
    <!--<div style="display: flex; justify-content: space-between; margin-top: 20px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.2); font-size: 14px;">
        <div>
            <i class="fas fa-map-marker-alt" style="margin-right: 5px;"></i> Aktif Turlar: 
            <strong><?= count($duraklar) ?></strong>
        </div>
        <div>
            <i class="fas fa-check-circle" style="margin-right: 5px;"></i> Tamamlanan: 
            <strong>
                <?php
                $tamamlananSayi = 0;
                foreach ($duraklar as $durak) {
                    if (isset($durak['teslim_edildi']) && $durak['teslim_edildi'] == 1) {
                        $tamamlananSayi++;
                    }
                }
                echo $tamamlananSayi;
                ?>
            </strong>
        </div>
        <div>
            <i class="fas fa-clock" style="margin-right: 5px;"></i> Bekleyen: 
            <strong>
                <?php
                $bekleyenSayi = 0;
                foreach ($duraklar as $durak) {
                    if (isset($durak['bekleniyor']) && $durak['bekleniyor'] == 1) {
                        $bekleyenSayi++;
                    }
                }
                echo $bekleyenSayi;
                ?>
            </strong>
        </div>
        <div>
            <i class="fas fa-user" style="margin-right: 5px;"></i> Aktif Şoförler: 
            <strong>
                <?php
                $soforler = [];
                foreach ($duraklar as $durak) {
                    if (isset($durak['sofor_adi']) && !empty($durak['sofor_adi'])) {
                        $soforler[$durak['sofor_adi']] = true;
                    }
                }
                echo count($soforler);
                ?>
            </strong>
        </div>
    </div>-->
</div>

<!-- Canlı saat için JavaScript -->
<script>
    function updateTime() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        
        document.getElementById('current-time').textContent = `${hours}:${minutes}:${seconds}`;
        
        setTimeout(updateTime, 1000);
    }
    
    // Sayfa yüklendiğinde saati başlat
    document.addEventListener('DOMContentLoaded', updateTime);
</script>

    <div class="dashboard-header">
        <div class="dashboard-title">
            <i class="fas fa-route"></i> Bugünün Turları
        </div>
        <div class="dashboard-stats">
            <div class="stat-box">
                <div class="stat-number" style="color: #28a745;">
                    <?php 
                    $tamamlananTurlar = array_filter($duraklar, function($durak) {
                        return isset($durak['teslim_edildi']) && $durak['teslim_edildi'] == 1;
                    });
                    echo count($tamamlananTurlar);
                    ?>
                </div>
                <div class="stat-label">Tamamlanan</div>
            </div>
            <div class="stat-box">
                <div class="stat-number" style="color: #ffc107;">
                    <?php 
                    $bekleyenTurlar = array_filter($duraklar, function($durak) {
                        return isset($durak['bekleniyor']) && $durak['bekleniyor'] == 1;
                    });
                    echo count($bekleyenTurlar);
                    ?>
                </div>
                <div class="stat-label">Bekleyen</div>
            </div>
            <div class="stat-box">
                <div class="stat-number" style="color: #6c757d;">
                    <?php echo count($duraklar); ?>
                </div>
                <div class="stat-label">Toplam</div>
            </div>
        </div>
    </div>
    
    <div class="tours-container">
        <div class="tours-header">
            <h3 class="tours-title">
                <i class="fas fa-truck"></i> Aktif Turlar
            </h3>
        </div>
        
        <?php if (empty($duraklar)): ?>
            <div class="no-tours">
                <i class="fas fa-route"></i>
                <p>Bugün için planlanmış tur bulunmamaktadır.</p>
            </div>
        <?php else: ?>
            <div class="tours-grid">
                <?php foreach ($duraklar as $durak): ?>
                                        <?php if (isset($tur['paket_durumu']) && !empty($tur['paket_durumu'])): ?>
                                            <div class="paket-bildirim <?= 
                                                $tur['paket_durumu'] == 'Hazır' ? 'paket-hazir' : 
                                                ($tur['paket_durumu'] == 'Hazırlanıyor' ? 'paket-hazirlaniyor' : 'paket-beklemede') 
                                            ?>">
                                                Paket Durumu: <?= $tur['paket_durumu'] ?>
                                            </div>
                                        <?php endif; ?>
                    
                    <div class="tour-card <?= $turTamamlanmis ? 'tour-completed' : '' ?>">
                        <div class="tour-status-indicator <?= $statusClass ?>"></div>
                        
                        <?php if ($turTamamlanmis): ?>
                            <div class="tour-completed-badge">
                                <i class="fas fa-check"></i> TAMAMLANDI
                            </div>
                        <?php endif; ?>
                        
                        <div class="tour-header">
                            <div class="tour-route">
                                <span class="tour-city" title="<?= $durak['il_adi'] ?? 'Bilinmeyen' ?>">
                                    <?= strlen($durak['il_adi']) > 8 ? substr($durak['il_adi'], 0, 8).'...' : $durak['il_adi'] ?>
                                </span>
                                <span class="tour-arrow">
                                    <i class="fas fa-long-arrow-alt-right"></i>
                                </span>
                                <span class="tour-city" title="<?= $durak['ilce_adi'] ?? 'Bilinmeyen' ?>">
                                    <?= strlen($durak['ilce_adi']) > 8 ? substr($durak['ilce_adi'], 0, 8).'...' : $durak['ilce_adi'] ?>
                                </span>
                            </div>
                            
                            <div class="tour-status <?= $statusBadgeClass ?>">
                                <i class="fas <?= $statusIcon ?>"></i>
                                <?= $statusText ?>
                            </div>
                        </div>
                        
                        <div class="tour-body">
                            <div class="tour-info-row">
                                <div class="tour-info-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="tour-info-content">
                                    <span class="tour-info-label">Durak:</span>
                                    <?= $durak['durak_adi'] ?? 'Bilinmeyen' ?>
                                </div>
                            </div>
                            
                            <div class="tour-info-row">
                                <div class="tour-info-icon">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="tour-info-content">
                                    <span class="tour-info-label">Şoför:</span>
                                    <?= $durak['sofor_adi'] ?? 'Bilinmeyen' ?>
                                </div>
                            </div>
                            
                            <?php if (isset($durak['arac_plaka'])): ?>
                            <div class="tour-info-row">
                                <div class="tour-info-icon">
                                    <i class="fas fa-truck"></i>
                                </div>
                                <div class="tour-info-content">
                                    <span class="tour-info-label">Araç:</span>
                                    <?= $durak['arac_plaka'] ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="tour-footer">
                            <span>
                                <i class="far fa-clock"></i>
                                <?= isset($durak['guncelleme_zamani']) ? date('d.m.Y H:i', strtotime($durak['guncelleme_zamani'])) : 'Bilinmiyor' ?>
                            </span>
                            
                            <?php if (isset($durak['tur_id'])): ?>
                            <span>
                                <i class="fas fa-hashtag"></i>
                                Tur #<?= $durak['tur_id'] ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- İstatistik Kartları -->
    <div class="tours-container">
        <div class="tours-header">
            <h3 class="tours-title">
                <i class="fas fa-chart-pie"></i> Tur İstatistikleri
            </h3>
        </div>
        
        <div style="padding: 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">
            <!-- Şoför Bazlı İstatistikler -->
            <div class="stat-card" style="background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 15px; border: 1px solid #eaeaea;">
                <h4 style="margin-top: 0; color: #333; font-size: 16px; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px;">
                    <i class="fas fa-user" style="color: #4a6cf7; margin-right: 8px;"></i> Şoför Performansı
                </h4>
                
                <?php
                // Şoför bazlı istatistikler
                $soforIstatistikleri = [];
                foreach ($duraklar as $durak) {
                    $soforAdi = $durak['sofor_adi'] ?? 'Bilinmeyen';
                    
                    if (!isset($soforIstatistikleri[$soforAdi])) {
                        $soforIstatistikleri[$soforAdi] = [
                            'toplam' => 0,
                            'tamamlanan' => 0
                        ];
                    }
                    
                    $soforIstatistikleri[$soforAdi]['toplam']++;
                    
                    if (isset($durak['teslim_edildi']) && $durak['teslim_edildi'] == 1) {
                        $soforIstatistikleri[$soforAdi]['tamamlanan']++;
                    }
                }
                
                // En fazla 5 şoför göster
                $count = 0;
                foreach ($soforIstatistikleri as $sofor => $istatistik):
                    if ($count++ >= 5) break;
                    $yuzde = $istatistik['toplam'] > 0 ? round(($istatistik['tamamlanan'] / $istatistik['toplam']) * 100) : 0;
                ?>
                    <div style="margin-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span style="font-size: 14px;"><?= $sofor ?></span>
                            <span style="font-size: 14px; font-weight: 500;"><?= $istatistik['tamamlanan'] ?>/<?= $istatistik['toplam'] ?></span>
                        </div>
                        <div style="height: 8px; background: #f0f0f0; border-radius: 4px; overflow: hidden;">
                            <div style="height: 100%; width: <?= $yuzde ?>%; background: #4a6cf7;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (count($soforIstatistikleri) == 0): ?>
                    <div style="text-align: center; color: #6c757d; padding: 15px 0;">
                        <i class="fas fa-info-circle"></i> Veri bulunamadı
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Bölge Bazlı İstatistikler -->
            <div class="stat-card" style="background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 15px; border: 1px solid #eaeaea;">
                <h4 style="margin-top: 0; color: #333; font-size: 16px; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px;">
                    <i class="fas fa-map-marked-alt" style="color: #4a6cf7; margin-right: 8px;"></i> Bölge Dağılımı
                </h4>
                
                <?php
                // Bölge bazlı istatistikler
                $bolgeIstatistikleri = [];
                foreach ($duraklar as $durak) {
                    $ilAdi = $durak['il_adi'] ?? 'Bilinmeyen';
                    
                    if (!isset($bolgeIstatistikleri[$ilAdi])) {
                        $bolgeIstatistikleri[$ilAdi] = 0;
                    }
                    
                    $bolgeIstatistikleri[$ilAdi]++;
                }
                
                // Değere göre sırala
                arsort($bolgeIstatistikleri);
                
                // En fazla 5 bölge göster
                $count = 0;
                $toplamTur = count($duraklar);
                foreach ($bolgeIstatistikleri as $bolge => $sayi):
                    if ($count++ >= 5) break;
                    $yuzde = $toplamTur > 0 ? round(($sayi / $toplamTur) * 100) : 0;
                ?>
                    <div style="margin-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span style="font-size: 14px;"><?= $bolge ?></span>
                            <span style="font-size: 14px; font-weight: 500;"><?= $sayi ?> tur (<?= $yuzde ?>%)</span>
                        </div>
                        <div style="height: 8px; background: #f0f0f0; border-radius: 4px; overflow: hidden;">
                            <div style="height: 100%; width: <?= $yuzde ?>%; background: #28a745;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (count($bolgeIstatistikleri) == 0): ?>
                    <div style="text-align: center; color: #6c757d; padding: 15px 0;">
                        <i class="fas fa-info-circle"></i> Veri bulunamadı
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Durum Özeti -->
            <div class="stat-card" style="background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 15px; border: 1px solid #eaeaea;">
                <h4 style="margin-top: 0; color: #333; font-size: 16px; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px;">
                    <i class="fas fa-tasks" style="color: #4a6cf7; margin-right: 8px;"></i> Durum Özeti
                </h4>
                
                <?php
                $tamamlananSayi = count($tamamlananTurlar);
                $bekleyenSayi = count($bekleyenTurlar);
                $hataSayi = count($duraklar) - $tamamlananSayi - $bekleyenSayi;
                
                $toplamTur = count($duraklar);
                $tamamlananYuzde = $toplamTur > 0 ? round(($tamamlananSayi / $toplamTur) * 100) : 0;
                $bekleyenYuzde = $toplamTur > 0 ? round(($bekleyenSayi / $toplamTur) * 100) : 0;
                $hataYuzde = $toplamTur > 0 ? round(($hataSayi / $toplamTur) * 100) : 0;
                ?>
                
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span style="font-size: 14px; display: flex; align-items: center;">
                            <i class="fas fa-check-circle" style="color: #28a745; margin-right: 5px;"></i> Tamamlanan
                        </span>
                        <span style="font-size: 14px; font-weight: 500;"><?= $tamamlananSayi ?> (<?= $tamamlananYuzde ?>%)</span>
                    </div>
                    <div style="height: 8px; background: #f0f0f0; border-radius: 4px; overflow: hidden;">
                        <div style="height: 100%; width: <?= $tamamlananYuzde ?>%; background: #28a745;"></div>
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span style="font-size: 14px; display: flex; align-items: center;">
                            <i class="fas fa-clock" style="color: #ffc107; margin-right: 5px;"></i> Bekleyen
                        </span>
                        <span style="font-size: 14px; font-weight: 500;"><?= $bekleyenSayi ?> (<?= $bekleyenYuzde ?>%)</span>
                    </div>
                    <div style="height: 8px; background: #f0f0f0; border-radius: 4px; overflow: hidden;">
                        <div style="height: 100%; width: <?= $bekleyenYuzde ?>%; background: #ffc107;"></div>
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span style="font-size: 14px; display: flex; align-items: center;">
                            <i class="fas fa-exclamation-triangle" style="color: #dc3545; margin-right: 5px;"></i> Hata
                        </span>
                        <span style="font-size: 14px; font-weight: 500;"><?= $hataSayi ?> (<?= $hataYuzde ?>%)</span>
                    </div>
                    <div style="height: 8px; background: #f0f0f0; border-radius: 4px; overflow: hidden;">
                        <div style="height: 100%; width: <?= $hataYuzde ?>%; background: #dc3545;"></div>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <div style="font-size: 24px; font-weight: bold; color: #4a6cf7;">
                        <?= $toplamTur ?>
                    </div>
                    <div style="font-size: 12px; color: #6c757d; text-transform: uppercase; letter-spacing: 1px;">
                        Toplam Tur
                    </div>
                </div>
            </div>
            
            <!-- Günlük Aktivite -->
            <div class="stat-card" style="background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 15px; border: 1px solid #eaeaea;">
                <h4 style="margin-top: 0; color: #333; font-size: 16px; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px;">
                    <i class="fas fa-calendar-day" style="color: #4a6cf7; margin-right: 8px;"></i> Günlük Aktivite
                </h4>
                
                <div style="text-align: center; padding: 15px 0;">
                    <div style="font-size: 16px; font-weight: bold; margin-bottom: 5px;">
                        <?= date('d F Y') ?>
                    </div>
                    <div style="font-size: 14px; color: #6c757d;">
                        <?= date('l') ?>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: space-around; text-align: center; margin-top: 15px;">
                    <div>
                        <div style="font-size: 20px; font-weight: bold; color: #28a745;">
                            <?= $tamamlananSayi ?>
                        </div>
                        <div style="font-size: 12px; color: #6c757d;">
                            Tamamlanan
                        </div>
                    </div>
                    
                    <div style="border-left: 1px solid #f0f0f0; border-right: 1px solid #f0f0f0; padding: 0 20px;">
                        <div style="font-size: 20px; font-weight: bold; color: #ffc107;">
                            <?= $bekleyenSayi ?>
                        </div>
                        <div style="font-size: 12px; color: #6c757d;">
                            Bekleyen
                        </div>
                    </div>
                    
                    <div>
                        <div style="font-size: 20px; font-weight: bold; color: #dc3545;">
                            <?= $hataSayi ?>
                        </div>
                        <div style="font-size: 12px; color: #6c757d;">
                            Hata
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #f0f0f0; text-align: center; font-size: 13px; color: #6c757d;">
                    <i class="fas fa-sync-alt"></i> Son güncelleme: <?= date('H:i:s') ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Hızlı Erişim Kartları -->
    <div class="quick-access" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">
        <a href="#" style="text-decoration: none; color: inherit;">
            <div style="background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 15px; text-align: center; transition: all 0.3s ease; border: 1px solid #eaeaea;">
                <i class="fas fa-plus-circle" style="font-size: 24px; color: #4a6cf7; margin-bottom: 10px;"></i>
                <div style="font-weight: 500;">Yeni Tur Ekle</div>
            </div>
        </a>
        
        <a href="#" style="text-decoration: none; color: inherit;">
            <div style="background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 15px; text-align: center; transition: all 0.3s ease; border: 1px solid #eaeaea;">
                <i class="fas fa-file-export" style="font-size: 24px; color: #28a745; margin-bottom: 10px;"></i>
                <div style="font-weight: 500;">Rapor Oluştur</div>
            </div>
        </a>
        
        <a href="#" style="text-decoration: none; color: inherit;">
            <div style="background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 15px; text-align: center; transition: all 0.3s ease; border: 1px solid #eaeaea;">
                <i class="fas fa-cog" style="font-size: 24px; color: #6c757d; margin-bottom: 10px;"></i>
                <div style="font-weight: 500;">Ayarlar</div>
            </div>
        </a>
        
        <a href="#" style="text-decoration: none; color: inherit;">
            <div style="background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 15px; text-align: center; transition: all 0.3s ease; border: 1px solid #eaeaea;">
                <i class="fas fa-question-circle" style="font-size: 24px; color: #17a2b8; margin-bottom: 10px;"></i>
                <div style="font-weight: 500;">Yardım</div>
            </div>
        </a>
    </div>
</div>

<!-- Otomatik yenileme için JavaScript -->
<script>
    // Sayfayı 60 saniyede bir yenile
    setTimeout(function() {
        location.reload();
    }, 60000);
    
    // Kart hover efektleri
    document.addEventListener('DOMContentLoaded', function() {
        const quickAccessCards = document.querySelectorAll('.quick-access > a > div');
        
        quickAccessCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = '0 8px 15px rgba(0,0,0,0.1)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
            });
        });
    });
</script>


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
