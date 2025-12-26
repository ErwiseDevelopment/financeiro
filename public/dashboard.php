<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];

// 1. CAPTURA O MÊS DO FILTRO (Igual à sua Index)
$mes_filtro = $_GET['mes'] ?? date('Y-m');

// 2. BUSCA TOTAIS DO MÊS SELECIONADO
$stmt_totais = $pdo->prepare("SELECT 
    SUM(CASE WHEN contatipo = 'Entrada' THEN contavalor ELSE 0 END) as e_total,
    SUM(CASE WHEN contatipo = 'Saída' THEN contavalor ELSE 0 END) as s_total,
    SUM(CASE WHEN contatipo = 'Entrada' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) as e_paga,
    SUM(CASE WHEN contatipo = 'Saída' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) as s_paga
    FROM contas WHERE usuarioid = ? AND contacompetencia = ?");
$stmt_totais->execute([$uid, $mes_filtro]);
$res = $stmt_totais->fetch();

$e_total = $res['e_total'] ?? 0;
$s_total = $res['s_total'] ?? 0;
$saldo_real = ($res['e_paga'] ?? 0) - ($res['s_paga'] ?? 0);
$saldo_previsto = $e_total - $s_total;

// Cálculo de Eficiência (Quanto sobrou das receitas)
$taxa_poupanca = ($e_total > 0) ? (($e_total - $s_total) / $e_total) * 100 : 0;

// 3. DADOS PARA O GRÁFICO DE CATEGORIAS (Maiores gastos)
$stmt_cat = $pdo->prepare("SELECT cat.categoriadescricao as label, SUM(c.contavalor) as total 
    FROM contas c 
    JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contacompetencia = ? AND c.contatipo = 'Saída'
    GROUP BY cat.categoriadescricao ORDER BY total DESC LIMIT 5");
$stmt_cat->execute([$uid, $mes_filtro]);
$dados_cat = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

$labels_cat = json_encode(array_column($dados_cat, 'label'));
$valores_cat = json_encode(array_column($dados_cat, 'total'));
?>

<style>
    :root { --p-color: #0d6efd; --s-color: #198754; --d-color: #dc3545; }
    body { background-color: #f4f7fa; font-family: 'Inter', sans-serif; }
    
    /* Seletor de Meses */
    .month-nav { display: flex; overflow-x: auto; gap: 10px; padding: 10px 0; scrollbar-width: none; }
    .month-nav::-webkit-scrollbar { display: none; }
    .btn-m { 
        padding: 8px 18px; border-radius: 12px; background: #fff; border: 1px solid #eee;
        color: #6c757d; font-weight: 600; font-size: 0.8rem; text-decoration: none; transition: 0.2s;
        white-space: nowrap;
    }
    .btn-m.active { background: var(--p-color); color: #fff; border-color: var(--p-color); box-shadow: 0 4px 10px rgba(13,110,253,0.2); }

    /* Cards e Gráficos */
    .card-dashboard { border: none; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); background: #fff; }
    .chart-box { position: relative; height: 220px; width: 100%; }
    .icon-box { width: 45px; height: 45px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
</style>

<div class="container py-4 mb-5">
    
    <div class="d-flex justify-content-between align-items-center mb-3 px-1">
        <div>
            <h4 class="fw-bold m-0">Dashboard</h4>
            <small class="text-muted">Análise de performance</small>
        </div>
        <div class="icon-box bg-white shadow-sm border text-primary">
            <i class="bi bi-bar-chart-fill"></i>
        </div>
    </div>

    <nav class="month-nav mb-4">
        <?php for($i = -2; $i <= 3; $i++): 
            $m = date('Y-m', strtotime("+$i month", strtotime(date('Y-m-01'))));
            $active = ($mes_filtro == $m) ? 'active' : '';
            $nome_mes = new IntlDateFormatter('pt_BR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'MMM yy');
            $label = $nome_mes->format(strtotime($m."-01"));
        ?>
            <a href="?mes=<?= $m ?>" class="btn-m <?= $active ?>"><?= ucfirst($label) ?></a>
        <?php endfor; ?>
    </nav>

    <div class="card-dashboard p-4 mb-4">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <span class="text-muted small fw-bold text-uppercase">Eficiência de Gastos</span>
                <h2 class="fw-bold mb-0"><?= number_format($taxa_poupanca, 1, ',', '.') ?>%</h2>
            </div>
            <span class="badge <?= $taxa_poupanca >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> rounded-pill px-3 py-2">
                <?= $taxa_poupanca >= 0 ? 'Positivo' : 'Negativo' ?>
            </span>
        </div>
        <div class="progress" style="height: 8px; border-radius: 10px;">
            <div class="progress-bar <?= $taxa_poupanca > 20 ? 'bg-success' : ($taxa_poupanca > 0 ? 'bg-info' : 'bg-danger') ?>" 
                 style="width: <?= max(5, min(100, $taxa_poupanca)) ?>%"></div>
        </div>
        <p class="text-muted small mt-2 mb-0">
            <?= $taxa_poupanca > 0 ? "Você economizou <b>R$ ".number_format($saldo_previsto, 2, ',', '.')."</b> do que recebeu." : "Suas despesas superaram suas receitas neste mês." ?>
        </p>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card-dashboard p-4 h-100">
                <h6 class="fw-bold mb-4">Balanço Mensal</h6>
                <div class="chart-box">
                    <canvas id="chartBalanço"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card-dashboard p-4 h-100">
                <h6 class="fw-bold mb-4">Gastos por Categoria</h6>
                <div class="chart-box">
                    <canvas id="chartCategorias"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card-dashboard p-4">
        <h6 class="fw-bold mb-4">Ranking de Despesas</h6>
        <?php if(empty($dados_cat)): ?>
            <div class="text-center py-4 text-muted small">Nenhum gasto registrado.</div>
        <?php else: foreach($dados_cat as $c): 
            $perc = ($s_total > 0) ? ($c['total'] / $s_total) * 100 : 0;
        ?>
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-bold"><?= $c['label'] ?></span>
                    <span class="small fw-bold">R$ <?= number_format($c['total'], 2, ',', '.') ?></span>
                </div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar bg-primary opacity-75" style="width: <?= $perc ?>%"></div>
                </div>
                <small class="text-muted" style="font-size: 0.65rem;"><?= number_format($perc, 1) ?>% de todas as despesas</small>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Gráfico de Balanço (Barras)
    new Chart(document.getElementById('chartBalanço'), {
        type: 'bar',
        data: {
            labels: ['Receitas', 'Despesas'],
            datasets: [{
                data: [<?= $e_total ?>, <?= $s_total ?>],
                backgroundColor: ['#198754', '#dc3545'],
                borderRadius: 10,
                barThickness: 50
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, grid: { display: false } }, x: { grid: { display: false } } }
        }
    });

    // Gráfico de Categorias (Doughnut)
    new Chart(document.getElementById('chartCategorias'), {
        type: 'doughnut',
        data: {
            labels: <?= $labels_cat ?>,
            datasets: [{
                data: <?= $valores_cat ?>,
                backgroundColor: ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#fd7e14'],
                borderWidth: 0,
                cutout: '75%'
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, font: { size: 10 } } }
            }
        }
    });
</script>

<?php require_once "../includes/footer.php"; ?>