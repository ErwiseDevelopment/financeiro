<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');

// 1. DADOS DE RESUMO E SAÚDE FINANCEIRA
$stmt_totais = $pdo->prepare("SELECT 
    SUM(CASE WHEN contatipo = 'Entrada' THEN contavalor ELSE 0 END) as e_total,
    SUM(CASE WHEN contatipo = 'Saída' THEN contavalor ELSE 0 END) as s_total,
    COUNT(contasid) as total_lancamentos
    FROM contas WHERE usuarioid = ? AND contacompetencia = ?");
$stmt_totais->execute([$uid, $mes_filtro]);
$res = $stmt_totais->fetch();

$e_total = $res['e_total'] ?? 0;
$s_total = $res['s_total'] ?? 0;
$saldo_previsto = $e_total - $s_total;
$taxa_poupanca = ($e_total > 0) ? (($e_total - $s_total) / $e_total) * 100 : 0;

// 2. DADOS PARA O GRÁFICO DE ROSCA (CATEGORIAS)
$stmt_cat = $pdo->prepare("SELECT cat.categoriadescricao as label, SUM(c.contavalor) as total 
    FROM contas c 
    JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contacompetencia = ? AND c.contatipo = 'Saída'
    GROUP BY cat.categoriadescricao ORDER BY total DESC LIMIT 5");
$stmt_cat->execute([$uid, $mes_filtro]);
$dados_cat = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

// 3. DADOS PARA O GRÁFICO DE BARRAS (ENTRADAS VS SAÍDAS)
$labels_comparativo = ['Receitas', 'Despesas'];
$valores_comparativo = [$e_total, $s_total];
?>

<style>
    :root { --p-color: #4e73df; --s-color: #1cc88a; --i-color: #36b9cc; }
    body { background-color: #f0f2f5; }
    .card-stat { border: none; border-radius: 15px; transition: transform 0.2s; }
    .card-stat:hover { transform: translateY(-5px); }
    .icon-circle { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
    .chart-container { position: relative; height: 250px; width: 100%; }
    .progress-thin { height: 6px; border-radius: 10px; }
</style>

<div class="container py-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold m-0">Dashboard <span class="text-primary text-xs">Analytics</span></h4>
        <div class="badge bg-white text-dark border shadow-sm p-2 rounded-3">
            <i class="bi bi-calendar3 me-1"></i> <?= date('M/Y', strtotime($mes_filtro)) ?>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card card-stat shadow-sm p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <small class="text-muted fw-bold text-uppercase" style="font-size: 0.65rem;">Eficiência Mensal</small>
                        <h4 class="fw-bold mb-0"><?= number_format($taxa_poupanca, 1) ?>%</h4>
                        <small class="text-<?= $taxa_poupanca > 0 ? 'success' : 'danger' ?> fw-bold">
                            <?= $taxa_poupanca > 0 ? 'Sobra de caixa' : 'Déficit no mês' ?>
                        </small>
                    </div>
                    <div class="icon-circle bg-primary-subtle text-primary">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                </div>
                <div class="progress progress-thin mt-3">
                    <div class="progress-bar <?= $taxa_poupanca > 20 ? 'bg-success' : 'bg-warning' ?>" style="width: <?= max(0, min(100, $taxa_poupanca)) ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card card-stat shadow-sm p-4 bg-white h-100">
                <h6 class="fw-bold mb-4">Balanço Financeiro</h6>
                <div class="chart-container">
                    <canvas id="chartComparativo"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-stat shadow-sm p-4 bg-white h-100">
                <h6 class="fw-bold mb-4">Maiores Gastos</h6>
                <div class="chart-container">
                    <canvas id="chartCategorias"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-stat shadow-sm p-4 bg-white mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold m-0">Detalhamento de Categorias</h6>
            <i class="bi bi-three-dots-vertical text-muted"></i>
        </div>
        <?php foreach($dados_cat as $c): 
            $perc = ($s_total > 0) ? ($c['total'] / $s_total) * 100 : 0;
        ?>
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-bold text-secondary"><?= $c['label'] ?></span>
                    <span class="small fw-bold">R$ <?= number_format($c['total'], 2, ',', '.') ?></span>
                </div>
                <div class="progress progress-thin">
                    <div class="progress-bar bg-primary" style="width: <?= $perc ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Configurações Globais
    Chart.defaults.font.family = 'Inter, sans-serif';
    Chart.defaults.color = '#858796';

    // 1. Gráfico de Comparação (Barras)
    new Chart(document.getElementById('chartComparativo'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels_comparativo) ?>,
            datasets: [{
                data: <?= json_encode($valores_comparativo) ?>,
                backgroundColor: ['#1cc88a', '#e74a3b'],
                borderRadius: 8,
                barThickness: 40
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, grid: { drawBorder: false, color: '#f2f2f2' } }, x: { grid: { display: false } } }
        }
    });

    // 2. Gráfico de Categorias (Doughnut)
    new Chart(document.getElementById('chartCategorias'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($dados_cat, 'label')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($dados_cat, 'total')) ?>,
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'],
                hoverOffset: 15,
                borderWidth: 4,
                borderColor: '#ffffff'
            }]
        },
        options: {
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true, padding: 15 } }
            }
        }
    });
</script>

<?php require_once "../includes/footer.php"; ?>