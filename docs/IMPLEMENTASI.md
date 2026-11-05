# Implementasi Toko Maya

Perubahan ini mengimplementasikan alur dasar empat peran berdasarkan laporan proyek. Perubahan dilanjutkan dari snapshot yang dipulihkan dan disiapkan untuk commit/push sesuai instruksi terbaru pengguna. Desain mengacu pada gambar di PDF (biru-putih, kartu katalog, dashboard per peran), bukan hasil pengukuran frame Figma. Ilustrasi SVG lokal merupakan placeholder yang ditandai; foto produk asli bisa diunggah oleh pemilik.

## Menjalankan

Prasyarat: PHP >= 8.2 beserta ekstensi Composer, PDO SQLite atau PDO MySQL, Composer, Node.js dan npm. Pada cloud ini gunakan `export PATH=/workspace/.cloud-setup/bin:$PATH` untuk PHP lokal.

```sh
composer install --prefer-source --no-interaction
npm ci
# Hanya jika .env belum ada:
# cp .env.example .env
# php artisan key:generate
# Untuk SQLite baru: touch database/database.sqlite
php artisan migrate
php artisan storage:link
npm run build
php artisan serve --no-reload --host=0.0.0.0 --port=8000
```

`--no-reload` diperlukan pada runtime PHP lokal cloud agar variabel konfigurasi PHP tetap diteruskan. Untuk frontend hot reload, jalankan `npm run dev -- --host 0.0.0.0` pada terminal kedua. Jika Vite sudah berhenti tetapi file `public/hot` tertinggal, hapus file generated tersebut agar aplikasi memakai hasil build.

Jalankan `php artisan schedule:work` di terminal terpisah saat pengembangan. Produksi perlu cron `* * * * * cd /path/aplikasi && php artisan schedule:run >> /dev/null 2>&1` agar pembatalan pesanan kedaluwarsa berjalan. Proses server dan scheduler tidak bertahan setelah pemulihan snapshot.

### Akun awal dan data contoh

- Produksi: jalankan `php artisan shop:create-owner email@toko.example --name="Nama Pemilik"`. Password diminta dengan input tersembunyi. Tidak ada password bawaan produksi.
- Demo lokal: isi `DEMO_PASSWORD` di `.env` dengan minimal 12 karakter, kemudian `php artisan db:seed --class=DemoShopSeeder`. Empat akun: `pemilik@demo.tokomaya.test`, `kasir@demo.tokomaya.test`, `admin_gudang@demo.tokomaya.test`, `pelanggan@demo.tokomaya.test`. Password menggunakan nilai lokal tersebut. Seeder tidak mengganti akun/produk yang sudah ada dan menolak lingkungan produksi.
- Demo mencakup 14 kategori dan 16 produk berlabel **Demo**, bukan 467/1.622 produk asli yang disebut laporan. Seeder normal tidak memasukkan akun/default password atau produk fiktif secara otomatis.
- `User.role` tidak dapat diisi melalui registrasi publik. Registrasi selalu menjadi pelanggan; pemilik mengelola akun staf.

### Katalog asli

Sediakan CSV UTF-8 dengan header:

```csv
sku,name,category,price,stock,weight,unit,description
BT-M10,Baut Hex M10,Baut dan Mur,2500,100,30,pcs,Baut untuk konstruksi
```

Harga rupiah bulat tanpa pemisah ribuan; berat gram. Kolom `description` opsional. Jalankan:

```sh
php artisan shop:import-products /path/produk.csv --dry-run
php artisan shop:import-products /path/produk.csv
```

Semua baris divalidasi sebelum penyimpanan. SKU duplikat/yang sudah ada ditolak; impor tidak menimpa stok atau data lama. Kegagalan penyimpanan membatalkan seluruh impor. Foto, merek dan spesifikasi dapat dilengkapi melalui manajemen produk. Produk baru dari dashboard berstok 0; penerimaan stok dicatat melalui gudang.

## Modul dan halaman

| Modul | Halaman / perilaku |
| --- | --- |
| Pelanggan | Beranda, pencarian/filter/sort katalog, detail, keranjang, registrasi/login, checkout, riwayat/status, tiket pengambilan, nomor resi, konfirmasi barang diterima, ulasan setelah pesanan selesai |
| Gudang | Antrean pesanan **lunas**, filter pengambilan/ekspedisi, siap diambil, input/koreksi resi, filter stok aman/menipis/habis, penyesuaian stok dengan alasan, riwayat pergerakan |
| Kasir | POS tunai/debit/QRIS, hitung total dan kembalian, struk cetak/PDF browser, pencarian kode pesanan, terima tunai pickup, konfirmasi penyerahan, monitoring online, retur dan riwayat |
| Pemilik | Dashboard penerimaan, kanal online/POS, filter periode, tren harian, lima produk terlaris, peringatan stok, ekspor CSV spreadsheet, cetak/PDF browser, tambah/edit/nonaktif produk, tambah kategori, manajemen pengguna/peran, catatan penyelesaian dana retur |

Tidak ada endpoint publik untuk mengubah stok/harga/status pembayaran. Middleware membatasi akses per peran; akun nonaktif ditolak pada setiap request terlindungi. Data pesanan pelanggan dibatasi pemilik pesanan; staf memiliki akses sesuai tugas. CSRF diterapkan pada semua formulir, pengecualian hanya endpoint webhook yang diverifikasi. Login dan endpoint pembayaran dibatasi frekuensinya. Waktu aplikasi default Asia/Jakarta; `APP_TIMEZONE` dapat diatur.

## Aturan stok, pembayaran, dan pemenuhan

- Harga, berat dan ongkir dihitung ulang di server; nilai total dari browser diabaikan.
- Stok tersedia **direservasi saat checkout**, maksimum 24 jam untuk pesanan belum lunas. Pengurangan memakai transaksi, urutan kunci produk konsisten, dan kondisi `stock >= quantity`. Stok fisik dapat dipahami sebagai stok tersedia + reservasi yang belum keluar.
- Setelah lunas, reservasi menjadi penjualan tanpa memotong stok lagi. Siap diambil/pengiriman/penyerahan juga tidak memotong stok lagi.
- Pesanan belum lunas yang batal/kedaluwarsa melepaskan reservasi tepat sekali. Pesanan lunas tidak dapat dibatalkan lewat endpoint pembatalan biasa. Pembayaran yang datang setelah stok dilepas perlu rekonsiliasi manual; sistem menolak menandainya lunas secara diam-diam.
- Form checkout dan POS membawa kunci request unik untuk mencegah transaksi ganda pada pengiriman ulang formulir yang sama.
- Alur pickup tunai: checkout → bayar kasir → diproses gudang → siap diambil → kasir menyerahkan. Ini menyelaraskan ketentuan gudang hanya menangani pesanan lunas.
- POS tunai/debit terverifikasi selesai langsung. Debit adalah pencatatan pembayaran EDC yang **sudah berhasil**, bukan integrasi EDC. QRIS/digital menunggu gateway.
- Ekspedisi wajib pembayaran digital, tidak ada COD. Setelah gudang mencatat resi, pelanggan melacak melalui situs ekspedisi dan dapat mengonfirmasi penerimaan.
- Retur hanya untuk pesanan selesai/lunas, dibatasi kuantitas tersisa. Barang cacat dikarantina dan tidak menambah stok layak jual. Barang layak jual dapat ditambahkan kembali dengan catatan stok. Catatan dana retur awalnya pending; pemilik baru mencatat selesai setelah transfer/kas benar-benar dilakukan. Aplikasi tidak mengirim refund otomatis.

## Integrasi Midtrans

Implementasi tersedia, namun **belum diuji dengan akun sandbox/live** karena kredensial merchant belum diberikan. Tes memakai HTTP fake dan tidak memanggil layanan pembayaran nyata.

1. Atur `MIDTRANS_SERVER_KEY` dan `MIDTRANS_CLIENT_KEY` secara aman di environment dan `MIDTRANS_PRODUCTION=false` untuk sandbox; jangan commit key.
2. Izinkan tujuan HTTPS `app.sandbox.midtrans.com` dan `api.sandbox.midtrans.com` pada kebijakan jaringan cloud. Produksi menggunakan `app.midtrans.com` dan `api.midtrans.com`. Pertahankan host lain yang sudah diperlukan.
3. Atur `APP_URL` ke domain HTTPS aplikasi yang benar. Daftarkan URL notifikasi publik `https://domain-aplikasi/payments/midtrans/webhook` pada Midtrans. URL localhost tidak dapat menerima webhook layanan eksternal.
4. Pilih metode yang diaktifkan akun merchant (QRIS, VA, e-wallet) pada halaman Snap. Batas expiry mengikuti sisa waktu reservasi pesanan.
5. Webhook memverifikasi SHA-512 signature, identitas pesanan, nominal, dan mengambil **status otoritatif melalui API Midtrans** sebelum menandai lunas. Signature notifikasi saja tidak cukup karena tidak menandatangani `transaction_status`. Notifikasi berulang tidak memotong stok lagi.
6. Sebelum go-live, verifikasi transaksi sandbox sukses/pending/ditolak/kedaluwarsa, pengiriman ulang notifikasi, refund/reconciliation, serta konfigurasi HTTPS dan jaringan. Tidak ada data kartu/PIN disimpan aplikasi.

## Pengiriman

Belum terhubung ke API tarif ekspedisi. `SHIPPING_JNE_PER_KG`, `SHIPPING_JNT_PER_KG`, dan `SHIPPING_SICEPAT_PER_KG` adalah **tarif toko dasar per kg**, ditampilkan sebagai tarif toko pada checkout, bukan tarif otomatis berdasarkan wilayah tujuan. Bila kosong, opsi ekspedisi dinonaktifkan. Perhitungan membulatkan berat total ke atas per kg dan menerapkan faktor wilayah Padang/sekitarnya dari config/shop.php di server. Tarif/faktor harus ditinjau toko sebelum produksi. Untuk tarif API sesuai tujuan dibutuhkan pemilihan penyedia layanan, kredensial, kode asal/tujuan dan pemetaan alamat; jangan gunakan tarif contoh sebagai tarif nasional produksi.

## Validasi dan batas kesiapan

```sh
php artisan test
npm run build
php artisan view:cache
```

Tes memakai SQLite memory terpisah dari database development. Skenario mencakup hak akses, registrasi anti-eskalasi, akun nonaktif, harga server, idempotensi checkout, stok berlebih, lifecycle pickup, expiry/cancel, webhook palsu/duplikat/status tidak cocok, POS, retur, audit stok, review, ekspor dan impor CSV. Sebelum produksi, uji concurrency dengan database target MySQL dan beban sesungguhnya; tes SQLite tidak membuktikan kinerja lintas server atau SLA 3 detik.

Yang masih memerlukan data/layanan eksternal: katalog dan foto resmi, tarif API logistik, akun Midtrans dan pengujian sandbox, deployment HTTPS, cron produksi, serta backup/restore database terjadwal. Tidak ada modul absensi, payroll, pajak akuntansi, atau GPS kurir sesuai batasan laporan. Laporan mendukung CSV, workbook Excel .xlsx asli, serta PDF server melalui Dompdf. Struk thermal memiliki pilihan kertas 58/80 mm dan pengaturan cetak browser. Angka laporan adalah penerimaan bruto (termasuk ongkir) dan catatan retur terpisah, bukan perhitungan laba.

## Hasil validasi instance pengembangan

- 58 tes otomatis dalam 5 kelompok; jumlah assertion mengikuti hasil pengujian terbaru; database tes SQLite memory.
- Build produksi Vite berhasil; Blade berhasil dikompilasi.
- Chromium: login empat peran, tambah keranjang, checkout tunai, penerimaan kasir, siap ambil gudang, dan penyerahan barang berhasil dengan data demo lokal.
- Chromium: 11 halaman utama diperiksa pada viewport desktop 1440 px dan seluler 390 px, tanpa overflow halaman atau kegagalan aset/JavaScript.
- Keranjang POS tetap tersimpan lintas pencarian, dapat mengirim beberapa produk, dan dibersihkan setelah transaksi berhasil.
- Midtrans hanya divalidasi dengan mock HTTP; belum ada transaksi eksternal atau pengujian merchant sesungguhnya.
- Riwayat commit dan push akhir dilaporkan pada hasil pengerjaan; arsip pemulihan sebelum perubahan tetap berada di luar checkout.

## Tambahan setelah pemulihan

Buku alamat (CRUD, default, pilihan modal), snapshot alamat checkout, kode ORD, tarif estimasi wilayah, pencarian POS instan nama/SKU, struk thermal 58/80 mm, verifikasi gudang, surat jalan dan label PDF, token SDK Midtrans Snap, statistik hari/bulan, grafik 12 bulan, unduhan XLSX asli dan PDF server sudah ditambahkan. Semua perubahan disimpan di checkout yang sama; arsip pemulihan terverifikasi berada di /workspace/backups dan tidak dimasukkan Git.

Akun demo dan perubahan database pengujian browser hanya untuk lingkungan pengembangan. Printer thermal belum diuji pada perangkat fisik; atur ukuran kertas, skala 100%, dan matikan header/footer browser. Live Midtrans membutuhkan key, domain notifikasi publik, dan akses jaringan yang benar. Selama pemasangan Composer melaporkan advisori pada dependensi lama; audit penuh ke packagist.org terhalang kebijakan jaringan lingkungan. Tinjau dan perbarui dependensi yang terdampak sebelum deployment produksi; tidak ada klaim siap produksi atau audit keamanan bersih.

Hasil pengujian akhir setelah penambahan fitur: 58 tes lulus dengan 262 assertions. Workbook unduhan dibaca menggunakan openpyxl (tipe sel angka diperiksa), dan teks PDF hasil unduhan diverifikasi dengan pdftotext. Chromium memvalidasi pilihan alamat modal, invoice, transaksi pickup, verifikasi gudang, unduhan dokumen, pencarian SKU instan dan viewport seluler. Integrasi eksternal Midtrans tetap menggunakan simulasi HTTP pada suite tes.

## Perbaikan peringatan PDO pada PHP 8.5

Alias `PDO::MYSQL_ATTR_SSL_CA` pada konfigurasi aplikasi diganti dengan resolusi konstanta driver `Pdo\Mysql::ATTR_SSL_CA` saat tersedia, dengan fallback untuk PHP 8.2/8.3. Laravel 11 juga mengevaluasi konfigurasi database bawaannya sebelum merge; bootstrapper konfigurasi aplikasi kini melewati hanya file database framework tersebut dan memakai konfigurasi database aplikasi yang lengkap. Defaults framework lain tetap dimuat. Tidak ada perubahan di vendor, tidak ada pengurangan tingkat error reporting, dan opsi CA untuk TLS tetap dipertahankan.

Pembaruan harus mencakup ketiga file runtime: app/Foundation/Bootstrap/LoadConfiguration.php, bootstrap/app.php, dan config/database.php. Jalankan composer dump-autoload, php artisan config:clear, lalu restart server dengan --no-reload. Pertahankan .env dan database. Tiga tes regresi konfigurasi ditambahkan di luar 58 tes fitur bisnis.

Validasi tambahan: image PHP resmi 8.5.12 dengan pdo_mysql aktif berhasil mereproduksi peringatan pada konfigurasi Laravel 11 asli. Bootstrap aplikasi yang diperbaiki lolos dengan deprecation dijadikan kegagalan pemeriksaan, serta opsi CA untuk MySQL/MariaDB dan defaults hashing/view tetap terjaga. Suite utama di PHP 8.4 lulus 61 tes / 272 assertions. Ini tidak menyatakan bahwa seluruh dependensi dan konfigurasi Windows telah diuji pada PHP 8.5.
