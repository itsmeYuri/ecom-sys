<?php
require_once __DIR__ . '/../includes/functions.php';
require_employee();

$available = (int)db()->query('SELECT COUNT(*) FROM products WHERE is_sold=0')->fetchColumn();
$sold      = (int)db()->query('SELECT COUNT(*) FROM products WHERE is_sold=1')->fetchColumn();
$orders    = (int)db()->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$categories= (int)db()->query('SELECT COUNT(*) FROM categories')->fetchColumn();

$stats = [
    'Available Products' => $available,
    'Products Sold'      => $sold,
    'Total Orders'       => $orders,
    'Categories'         => $categories,
];

// Monthly orders — last 6 months
$monthRows = db()->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS lbl,
           DATE_FORMAT(created_at,'%Y-%m') AS ym,
           COUNT(*) AS cnt
    FROM orders
    GROUP BY ym, lbl
    ORDER BY ym DESC
    LIMIT 6
")->fetchAll();
$monthRows = array_reverse($monthRows);
$monthLabels = array_column($monthRows, 'lbl');
$monthCounts = array_column($monthRows, 'cnt');

include __DIR__ . '/../header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Employee Dashboard</h1>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
    <?php foreach ($stats as $label => $value): ?>
    <div class="col-sm-6 col-lg-3">
        <div class="detail-box p-3 text-center">
            <h6 class="text-uppercase text-muted small"><?= e($label) ?></h6>
            <h3 class="mb-0 fw-bold"><?= $value ?></h3>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Charts -->
<div class="row g-4">
    <div class="col-lg-5">
        <div class="detail-box p-3">
            <h6 class="fw-bold mb-3 text-uppercase small text-muted">Product Status</h6>
            <canvas id="productChart" height="220"></canvas>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="detail-box p-3">
            <h6 class="fw-bold mb-3 text-uppercase small text-muted">Monthly Orders</h6>
            <canvas id="ordersChart" height="220"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function(){
    // Donut — product status
    new Chart(document.getElementById('productChart'), {
        type: 'doughnut',
        data: {
            labels: ['Available', 'Sold'],
            datasets: [{
                data: [<?= $available ?>, <?= $sold ?>],
                backgroundColor: ['#22c55e','#ef4444'],
                borderWidth: 2,
                borderColor: '#fff',
            }]
        },
        options: {
            cutout: '65%',
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 13 } } }
            }
        }
    });

    // Bar — monthly orders
    new Chart(document.getElementById('ordersChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($monthLabels ?: ['No data']) ?>,
            datasets: [{
                label: 'Orders',
                data: <?= json_encode($monthCounts ?: [0]) ?>,
                backgroundColor: '#3b82f6',
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            },
            plugins: { legend: { display: false } }
        }
    });
})();
</script>
<?php include __DIR__ . '/../footer.php'; ?>
