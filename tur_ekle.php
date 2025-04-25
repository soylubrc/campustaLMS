<?php
require_once 'config.php';

$mesaj = '';
$hata = '';

// Şoförleri getir
$stmt = $db->query("SELECT * FROM soforler WHERE durum = 'Aktif' ORDER BY ad, soyad");
$soforler = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Araçları getir
$stmt = $db->query("SELECT * FROM araclar WHERE durum = 'Aktif' ORDER BY plaka");
$araclar = $stmt->fetchAll(PDO::FETCH_ASSOC);

// İlleri getir
$stmt = $db->query("SELECT * FROM iller ORDER BY il_adi");
$iller = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Yük tiplerini getir
$stmt = $db->query("SELECT * FROM yuk_tipleri ORDER BY tip_adi");
$yuk_tipleri = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        // Tur bilgilerini ekle
        $stmt = $db->prepare("INSERT INTO turlar (sofor_id, arac_id, cikis_tarihi, cikis_saati, 
                             tahmini_donus_tarihi, tahmini_donus_saati, toplam_mesafe, durum) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, 'Planlandı')");
        
        $stmt->execute([
            $_POST['sofor_id'],
            $_POST['arac_id'],
            $_POST['cikis_tarihi'],
            $_POST['cikis_saati'],
            $_POST['tahmini_donus_tarihi'],
            $_POST['tahmini_donus_saati'],
            $_POST['toplam_mesafe']
        ]);
        
        $tur_id = $db->lastInsertId();
        
        // Durakları ekle
        $durak_sayisi = count($_POST['il_id']);
        
        for ($i = 0; $i < $durak_sayisi; $i++) {
            if (!empty($_POST['il_id'][$i])) {
                $stmt = $db->prepare("INSERT INTO tur_duraklar (tur_id, il_id, sira, yuk_tip_id, yuk_miktari) 
                                     VALUES (?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $tur_id,
                    $_POST['il_id'][$i],
                    $i + 1,
                    $_POST['yuk_tip_id'][$i],
                    $_POST['yuk_miktari'][$i]
                ]);
            }
        }
        
        // Şoför ve araç durumunu güncelle
        $db->prepare("UPDATE soforler SET durum = 'Yolda' WHERE sofor_id = ?")->execute([$_POST['sofor_id']]);
        $db->prepare("UPDATE araclar SET durum = 'Yolda' WHERE arac_id = ?")->execute([$_POST['arac_id']]);
        
        $db->commit();
        $mesaj = "Tur başarıyla oluşturuldu!";
    } catch (Exception $e) {
        $db->rollBack();
        $hata = "Hata oluştu: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Tur Ekle - Lojistik Firma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    /* Select2 özelleştirmeleri */
    .select2-container--bootstrap-5 .select2-selection {
        min-height: 38px;
        padding: 0.375rem 0.75rem;
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        padding-left: 0;
    }
    .select2-container--bootstrap-5 .select2-dropdown .select2-results__option--highlighted {
        background-color: #0d6efd;
    }
</style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Yeni Tur Ekle</h1>
            <a href="index.php" class="btn btn-secondary">Ana Sayfa</a>
        </div>
        
        <?php if ($mesaj): ?>
            <div class="alert alert-success"><?= $mesaj ?></div>
        <?php endif; ?>
        
        <?php if ($hata): ?>
            <div class="alert alert-danger"><?= $hata ?></div>
        <?php endif; ?>
        
        <form method="post" id="turForm">
            <div class="row">
 <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Tur Bilgileri</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="sofor_id" class="form-label">Şoför</label>
                                <select name="sofor_id" id="sofor_id" class="form-select" required>
                                    <option value="">Şoför Seçin</option>
                                    <?php foreach ($soforler as $sofor): ?>
                                        <option value="<?= $sofor['sofor_id'] ?>">
                                            <?= $sofor['ad'] . ' ' . $sofor['soyad'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="arac_id" class="form-label">Araç</label>
                                <select name="arac_id" id="arac_id" class="form-select" required>
                                    <option value="">Araç Seçin</option>
                                    <?php foreach ($araclar as $arac): ?>
                                        <option value="<?= $arac['arac_id'] ?>">
                                            <?= $arac['plaka'] . ' - ' . $arac['model'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="cikis_tarihi" class="form-label">Çıkış Tarihi</label>
                                        <input type="date" name="cikis_tarihi" id="cikis_tarihi" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="cikis_saati" class="form-label">Çıkış Saati</label>
                                        <input type="time" name="cikis_saati" id="cikis_saati" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="tahmini_donus_tarihi" class="form-label">Tahmini Dönüş Tarihi</label>
                                        <input type="date" name="tahmini_donus_tarihi" id="tahmini_donus_tarihi" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="tahmini_donus_saati" class="form-label">Tahmini Dönüş Saati</label>
                                        <input type="time" name="tahmini_donus_saati" id="tahmini_donus_saati" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="toplam_mesafe" class="form-label">Toplam Mesafe (km)</label>
                                <input type="number" name="toplam_mesafe" id="toplam_mesafe" class="form-control" readonly>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Duraklar</h5>
                            <button type="button" class="btn btn-light btn-sm" id="durakEkle">+ Durak Ekle</button>
                        </div>
                        <div class="card-body">
                            <div id="duraklar">
                                <div class="durak-item mb-3 p-3 border rounded">
                                    <h6 class="durak-sira mb-2">Durak #1</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-2">
                                                <label class="form-label">İl</label>
                                                <select name="il_id[]" class="form-select il-select select2-search" required>
                                                    <option value="">İl Seçin</option>
                                                    <?php foreach ($iller as $il): ?>
                                                        <option value="<?= $il['il_id'] ?>" data-mesafe="<?= $il['merkeze_uzaklik'] ?>">
                                                            <?= $il['il_adi'] ?> (<?= $il['merkeze_uzaklik'] ?> km)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-2">
                                                <label class="form-label">Yük Tipi</label>
                                                <select name="yuk_tip_id[]" class="form-select" required>
                                                    <option value="">Yük Tipi Seçin</option>
                                                    <?php foreach ($yuk_tipleri as $yuk_tipi): ?>
                                                        <option value="<?= $yuk_tipi['yuk_tip_id'] ?>">
                                                            <?= $yuk_tipi['tip_adi'] ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Yük Miktarı (kg)</label>
                                        <input type="number" name="yuk_miktari[]" class="form-control" step="0.1" min="0" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4 text-center">
                <button type="submit" class="btn btn-primary btn-lg">Turu Kaydet</button>
            </div>
        </form>
    </div>
<!-- jQuery ve Select2 JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        // Select2 başlatma
        $('.select2-search').select2({
            theme: 'bootstrap-5',
            placeholder: 'İl Seçin',
            allowClear: true,
            width: '100%',
            language: {
                noResults: function() {
                    return "Sonuç bulunamadı";
                },
                searching: function() {
                    return "Aranıyor...";
                }
            }
        });
        
        // Dinamik olarak eklenen select elementleri için
        $(document).on('select2:open', '.select2-search', function() {
            document.querySelector('.select2-search__field').focus();
        });
        
        // Yeni bir durak eklendiğinde Select2'yi başlat
        $(document).on('durak:added', function(e, durakElement) {
            $(durakElement).find('.select2-search').select2({
                theme: 'bootstrap-5',
                placeholder: 'İl Seçin',
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function() {
                        return "Sonuç bulunamadı";
                    },
                    searching: function() {
                        return "Aranıyor...";
                    }
                }
            });
        });
    });
</script>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let durakSayisi = 1;
            const durakEkleBtn = document.getElementById('durakEkle');
            const duraklar = document.getElementById('duraklar');
            
            // Durak ekleme
            durakEkleBtn.addEventListener('click', function() {
                durakSayisi++;
                
                const yeniDurak = document.createElement('div');
                yeniDurak.className = 'durak-item mb-3 p-3 border rounded';
                
                const durakHeader = document.createElement('div');
                durakHeader.className = 'd-flex justify-content-between align-items-center mb-2';
                
                const durakSira = document.createElement('h6');
                durakSira.className = 'durak-sira mb-0';
                durakSira.textContent = `Durak #${durakSayisi}`;
                
                const silBtn = document.createElement('button');
                silBtn.type = 'button';
                silBtn.className = 'btn btn-danger btn-sm durak-sil';
                silBtn.textContent = 'Sil';
                silBtn.addEventListener('click', function() {
                    yeniDurak.remove();
                    hesaplaToplam();
                    yenidenNumaralandir();
                });
                
                durakHeader.appendChild(durakSira);
                durakHeader.appendChild(silBtn);
                
                yeniDurak.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label class="form-label">İl</label>
                                <select name="il_id[]" class="form-select il-select" required>
                                    <option value="">İl Seçin</option>
                                    <?php foreach ($iller as $il): ?>
                                        <option value="<?= $il['il_id'] ?>" data-mesafe="<?= $il['merkeze_uzaklik'] ?>">
                                            <?= $il['il_adi'] ?> (<?= $il['merkeze_uzaklik'] ?> km)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label class="form-label">Yük Tipi</label>
                                <select name="yuk_tip_id[]" class="form-select" required>
                                    <option value="">Yük Tipi Seçin</option>
                                    <?php foreach ($yuk_tipleri as $yuk_tipi): ?>
                                        <option value="<?= $yuk_tipi['yuk_tip_id'] ?>">
                                            <?= $yuk_tipi['tip_adi'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Yük Miktarı (kg)</label>
                        <input type="number" name="yuk_miktari[]" class="form-control" step="0.1" min="0" required>
                    </div>
                `;
                
                yeniDurak.insertBefore(durakHeader, yeniDurak.firstChild);
                duraklar.appendChild(yeniDurak);
                
                // Yeni eklenen durak için il değişikliği dinleyicisi ekle
                const yeniIlSelect = yeniDurak.querySelector('.il-select');
                yeniIlSelect.addEventListener('change', hesaplaToplam);
            });
            
            // İl değişikliklerini dinle ve toplam mesafeyi hesapla
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('il-select')) {
                    hesaplaToplam();
                }
            });
            
            // İlk durak için il değişikliği dinleyicisi ekle
            const ilkIlSelect = document.querySelector('.il-select');
            ilkIlSelect.addEventListener('change', hesaplaToplam);
            
            // Toplam mesafeyi hesapla
            function hesaplaToplam() {
                const ilSelects = document.querySelectorAll('.il-select');
                let toplamMesafe = 0;
                
                ilSelects.forEach(select => {
                    const selectedOption = select.options[select.selectedIndex];
                    if (selectedOption && selectedOption.value) {
                        const mesafe = parseInt(selectedOption.dataset.mesafe);
                        if (!isNaN(mesafe)) {
                            toplamMesafe += mesafe * 2; // Gidiş-dönüş mesafesi
                        }
                    }
                });
                
                document.getElementById('toplam_mesafe').value = toplamMesafe;
            }
            
            // Durakları yeniden numaralandır
            function yenidenNumaralandir() {
                const durakSiralar = document.querySelectorAll('.durak-sira');
                durakSiralar.forEach((sira, index) => {
                    sira.textContent = `Durak #${index + 1}`;
                });
                durakSayisi = durakSiralar.length;
            }
        });
    </script>
</body>
</html>