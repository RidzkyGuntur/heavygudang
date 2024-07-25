<?php
include '../dbconnect.php';
include 'cek.php';

// Mendapatkan parameter dari request
$window_size = isset($_GET['window_size']) ? intval($_GET['window_size']) : 4;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Query untuk mengambil data stok dari barang masuk
$query_masuk = "SELECT idx, tgl, jumlah AS stock FROM sbrg_masuk ORDER BY idx, tgl ASC";
$result_masuk = mysqli_query($conn, $query_masuk);

// Query untuk mengambil data stok dari barang keluar
$query_keluar = "SELECT idx, tgl, -jumlah AS stock FROM sbrg_keluar ORDER BY idx, tgl ASC";
$result_keluar = mysqli_query($conn, $query_keluar);

$barang_data = [];

// Menggabungkan data masuk dan keluar
while ($row = mysqli_fetch_assoc($result_masuk)) {
    $barang_data[$row['idx']][] = ['tgl' => $row['tgl'], 'stock' => $row['stock']];
}
while ($row = mysqli_fetch_assoc($result_keluar)) {
    $barang_data[$row['idx']][] = ['tgl' => $row['tgl'], 'stock' => $row['stock']];
}

// Filter data berdasarkan tanggal
function filter_data_by_date($data, $start_date, $end_date) {
    $filtered_data = [];
    foreach ($data as $entry) {
        if ((!empty($start_date) && $entry['tgl'] < $start_date) || (!empty($end_date) && $entry['tgl'] > $end_date)) {
            continue;
        }
        $filtered_data[] = $entry;
    }
    return $filtered_data;
}

// Filter data berdasarkan tanggal
foreach ($barang_data as $idx => &$data) {
    $data = filter_data_by_date($data, $start_date, $end_date);
    array_multisort(array_column($data, 'tgl'), SORT_ASC, $data);
}

// Menghitung akumulasi stok per barang
$accumulated_stocks = [];
foreach ($barang_data as $idx => $data) {
    $current_stock = 0;
    foreach ($data as $entry) {
        $current_stock += $entry['stock'];
        // Pastikan stok tidak negatif
        $current_stock = max($current_stock, 0);
        $accumulated_stocks[$idx][] = $current_stock;
    }
}

// Fungsi untuk menghitung Simple Moving Average (SMA)
function calculate_sma($data, $window_size) {
    $sma = [];
    $data_count = count($data);

    for ($i = 0; $i < $data_count; $i++) {
        if ($i < $window_size - 1) {
            $sma[] = null; // Tidak cukup data untuk moving average
        } else {
            $window_data = array_slice($data, $i - $window_size + 1, $window_size);
            $window_sum = array_sum($window_data);
            $sma[] = round($window_sum / $window_size); // Membulatkan hasil SMA
        }
    }

    return $sma;
}

// Validasi window size agar tidak lebih besar dari jumlah data yang tersedia
$warnings = [];
$moving_averages = [];
foreach ($accumulated_stocks as $idx => $data) {
    if (count($data) < $window_size) {
        $warnings[$idx] = "Jumlah data tidak mencukupi untuk window size $window_size.";
        $moving_averages[$idx] = calculate_sma($data, count($data));
    } else {
        $moving_averages[$idx] = calculate_sma($data, $window_size);
    }
}

// Menghitung prediksi pembelian stok berikutnya dalam satuan
$predictions = [];
foreach ($moving_averages as $idx => $data) {
    $last_ma = end($data);
    if ($last_ma !== null) {
        $predictions[$idx] = round($last_ma);
    } else {
        $predictions[$idx] = null;
    }
}

// Menghitung MAPE dengan batas maksimal 100%
function calculate_mape($actual, $predicted) {
    $n = count($actual);
    $sum = 0;
    $valid_count = 0;
    for ($i = 0; $i < $n; $i++) {
        if ($actual[$i] != 0 && $predicted[$i] !== null) {
            $percentage_error = abs(($actual[$i] - $predicted[$i]) / $actual[$i]);
            $percentage_error = min($percentage_error, 1); // Membatasi error maksimal 100%
            $sum += $percentage_error;
            $valid_count++;
        }
    }
    return $valid_count > 0 ? round(($sum / $valid_count) * 100, 2) : 0; // Membulatkan MAPE
}

$mapes = [];
foreach ($accumulated_stocks as $idx => $data) {
    $mapes[$idx] = calculate_mape($data, $moving_averages[$idx]);
}

// Menghitung jumlah yang harus dibeli untuk pembelian stok berikutnya
$purchases = [];
foreach ($predictions as $idx => $prediction) {
    $last_stock = end($accumulated_stocks[$idx]);
    if ($prediction !== null && $last_stock < $prediction) {
        $purchases[$idx] = $prediction - $last_stock;
    } else {
        $purchases[$idx] = 0; // Tidak perlu membeli jika stok sudah mencukupi
    }
}

// Mengambil data tambahan barang dari sstock_brg
$query_barang = "SELECT * FROM sstock_brg";
$result_barang = mysqli_query($conn, $query_barang);

$barang_info = [];
while ($row = mysqli_fetch_assoc($result_barang)) {
    $barang_info[$row['idx']] = $row;
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Analisis Data Stok - Prediksi Pembelian</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/themify-icons.css">
    <link rel="stylesheet" href="assets/css/metisMenu.css">
    <link rel="stylesheet" href="assets/css/owl.carousel.min.css">
    <link rel="stylesheet" href="assets/css/slicknav.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.19/css/dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.18/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.3/css/responsive.bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.3/css/responsive.jqueryui.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.5.2/css/buttons.dataTables.min.css">
    <script async src="https://www.googletagmanager.com/gtag/js?id=UA-144808195-1"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'UA-144808195-1');
    </script>
    <link rel="stylesheet" href="assets/css/typography.css">
    <link rel="stylesheet" href="assets/css/default-css.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <script src="assets/js/vendor/modernizr-2.8.3.min.js"></script>
    <style>
        .modal-dialog-centered {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100% - 1rem);
        }
        .modal-content {
            border-radius: 1rem;
        }
        .modal-body p {
            margin-bottom: 1rem;
        }
        .warning {
            color: red;
        }
    </style>
</head>
<body>
    <div id="preloader">
        <div class="loader"></div>
    </div>
    <div class="page-container">
        <div class="sidebar-menu">
            <div class="sidebar-header">
                <a href="index.php"><img src="../heavy.png" alt="logo" width="40%"></a>
            </div>
            <div class="main-menu">
                <div class="menu-inner">
                    <nav>
                        <ul class="metismenu" id="menu">
                            <li><a href="index.php"><span>Notes</span></a></li>
                            <li><a href="stock.php"><i class="ti-dashboard"></i><span>Stock Barang</span></a></li>
                            <li>
                                <a href="javascript:void(0)" aria-expanded="true"><i class="ti-layout"></i><span>Transaksi Data</span></a>
                                <ul class="collapse">
                                    <li><a href="masuk.php">Barang Masuk</a></li>
                                    <li><a href="keluar.php">Barang Keluar</a></li>
                                    <li><a href="list_barang.php">List Barang Masuk / Keluar</a></li>
                                </ul>
                            </li>
                            <li class="active">
                                <a href="analisis_data.php"><i class="ti-bar-chart"></i><span>Analisis Data</span></a>
                            </li>
                            <li><a href="logout.php"><span>Logout</span></a></li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
        <div class="main-content">
            <div class="header-area">
                <div class="row align-items-center">
                    <div class="col-md-6 col-sm-8 clearfix">
                        <div class="nav-btn pull-left">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                    <div class="col-md-6 col-sm-4 clearfix">
                        <ul class="notification-area pull-right">
                            <li>
                                <h3>
                                    <div class="date">
                                        <script type='text/javascript'>
                                            var months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                                            var myDays = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                                            var date = new Date();
                                            var day = date.getDate();
                                            var month = date.getMonth();
                                            var thisDay = date.getDay(), thisDay = myDays[thisDay];
                                            var yy = date.getYear();
                                            var year = (yy < 1000) ? yy + 1900 : yy;
                                            document.write(thisDay + ', ' + day + ' ' + months[month] + ' ' + year);
                                        </script>
                                    </div>
                                </h3>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="page-title-area">
                <div class="row align-items-center">
                    <div class="col-sm-6">
                        <div class="breadcrumbs-area clearfix">
                            <h4 class="page-title pull-left">Dashboard</h4>
                            <ul class="breadcrumbs pull-left">
                                <li><a href="index.php">Home</a></li>
                                <li><span>Daftar Barang</span></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-sm-6 clearfix"></div>
                </div>
            </div>
            <div class="main-content-inner">
                <div class="row mt-5 mb-5">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-sm-flex justify-content-between align-items-center">
                                    <h2>Prediksi Stok</h2>
                                </div>
                                <div class="data-tables datatable-dark">
                                    <!-- Form untuk memilih periode analisis -->
                                    <form action="analisis_data.php" method="GET">
                                        <label for="window_size">Pilih Window Size:</label>
                                        <select name="window_size" id="window_size">
                                            <option value="3" <?php echo $window_size == 3 ? 'selected' : ''; ?>>3</option>
                                            <option value="4" <?php echo $window_size == 4 ? 'selected' : ''; ?>>4</option>
                                            <option value="5" <?php echo $window_size == 5 ? 'selected' : ''; ?>>5</option>
                                            <option value="6" <?php echo $window_size == 6 ? 'selected' : ''; ?>>6</option>
                                            <option value="7" <?php echo $window_size == 7 ? 'selected' : ''; ?>>7</option>
                                            <option value="8" <?php echo $window_size == 8 ? 'selected' : ''; ?>>8</option>
                                            <option value="9" <?php echo $window_size == 9 ? 'selected' : ''; ?>>9</option>
                                            <option value="10" <?php echo $window_size == 10 ? 'selected' : ''; ?>>10</option>
                                        </select>
                                        <label for="start_date">Tanggal Mulai:</label>
                                        <input type="date" id="start_date" name="start_date" value="<?php echo isset($start_date) ? $start_date : ''; ?>">
                                        <label for="end_date">Tanggal Akhir:</label>
                                        <input type="date" id="end_date" name="end_date" value="<?php echo isset($end_date) ? $end_date : ''; ?>">
                                        <button type="submit" class="btn btn-primary">Terapkan</button>
                                    </form>
                                    <br>
                                    <table id="dataTable3" class="display" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>Nama Barang</th>
                                                <th>Jenis</th>
                                                <th>Merk</th>
                                                <th>Stok Akumulasi</th>
                                                <th>Moving Average</th>
                                                <th>Prediksi Pembelian</th>
                                                <th>MAPE</th>
                                                <th>Peringatan</th>
                                                <th>Jumlah yang Harus Dibeli</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($barang_info as $idx => $info): ?>
                                                <tr>
                                                    <td><?php echo $info['nama']; ?></td>
                                                    <td><?php echo $info['jenis']; ?></td>
                                                    <td><?php echo $info['merk']; ?></td>
                                                    <td><?php echo isset($accumulated_stocks[$idx]) ? end($accumulated_stocks[$idx]) : 'N/A'; ?></td>
                                                    <td><?php echo isset($moving_averages[$idx]) ? end($moving_averages[$idx]) : 'N/A'; ?></td>
                                                    <td><?php echo isset($predictions[$idx]) ? $predictions[$idx] : 'N/A'; ?></td>
                                                    <td><?php echo isset($mapes[$idx]) && $mapes[$idx] !== null ? number_format($mapes[$idx], 2) . '%' : 'N/A'; ?></td>
                                                    <td><?php echo isset($warnings[$idx]) ? "<span class='warning'>{$warnings[$idx]}</span>" : ''; ?></td>
                                                    <td><?php echo isset($purchases[$idx]) ? $purchases[$idx] : 'N/A'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Button to trigger modal -->
                                <button type="button" class="btn btn-info" data-toggle="modal" data-target="#analysisModal">
                                   Tampilkan Hasil Analisis
                                </button>
                                <!-- Modal -->
                                <div class="modal fade" id="analysisModal" tabindex="-1" role="dialog" aria-labelledby="analysisModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="analysisModalLabel">Hasil Analisis</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <!-- Results will be populated here -->
                                                <?php foreach ($barang_info as $idx => $info): ?>
                                                    <?php
                                                    $nama = $info['nama'];
                                                    $prediksi_pembelian = isset($predictions[$idx]) ? $predictions[$idx] : 'N/A';
                                                    $mape = isset($mapes[$idx]) && $mapes[$idx] !== null ? number_format($mapes[$idx], 2) . '%' : 'N/A';
                                                    $warning = isset($warnings[$idx]) ? "<span class='warning'>{$warnings[$idx]}</span>" : '';
                                                    $purchase = isset($purchases[$idx]) ? $purchases[$idx] : 'N/A';
                                                    ?>
                                                    <p>
                                                        Barang: <?php echo $nama; ?>.<br>
                                                        Prediksi pembelian: <?php echo $prediksi_pembelian; ?> pcs.<br>
                                                        Tingkat akurasi (MAPE): <?php echo $mape; ?>.<br>
                                                        <?php echo $warning; ?><br>
                                                        Jumlah yang harus dibeli: <?php echo $purchase; ?> pcs.
                                                    </p>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <script>
                                    $(document).ready(function() {
                                        $('#dataTable3').DataTable();
                                    });
                                </script>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <footer>
            <div class="footer-area">
                <p>Heavy Cell</p>
            </div>
        </footer>
    </div>
    <script src="assets/js/vendor/jquery-2.2.4.min.js"></script>
    <script src="assets/js/popper.min.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>
    <script src="assets/js/owl.carousel.min.js"></script>
    <script src="assets/js/metisMenu.min.js"></script>
    <script src="assets/js/jquery.slimscroll.min.js"></script>
    <script src="assets/js/jquery.slicknav.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.5.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.5.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.js"></script>
    <script src="https://cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.18/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.3/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.3/js/responsive.bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.5.2/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.2/Chart.min.js"></script>
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://cdn.zingchart.com/zingchart.min.js"></script>
    <script>
        zingchart.MODULESDIR = "https://cdn.zingchart.com/modules/";
        ZC.LICENSE = ["569d52cefae586f634c54f86dc99e6a9", "ee6b7db5b51705a13dc2339db3edaf6d"];
    </script>
    <script src="assets/js/line-chart.js"></script>
    <script src="assets/js/pie-chart.js"></script>
    <script src="assets/js/plugins.js"></script>
    <script src="assets/js/scripts.js"></script>
</body>
</html>
