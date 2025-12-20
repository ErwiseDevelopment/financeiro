<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');

// 1. Totais por Categoria (Gastos)
$stmt_cat = $pdo->prepare("SELECT cat.categoriadescricao, SUM(c.contavalor) as total 
    FROM contas c 
    JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contacompetencia = ? AND c.contatipo = 'Saída'
    GROUP BY cat.categoriadescricao");
$stmt_cat->execute([$uid, $mes_filtro]);
$dados_categorias = $stmt_cat->fetchAll();

// Preparar dados para o gráfico de pizza
$labels_cat = [];
$valores_cat = [];
foreach($dados_categorias as $d) {
    $labels_cat[] = $d['categoriadescricao'];
    $valores_cat[] = $d['total'];
}

// 2. Balanço Mensal (Resumo)
$stmt_resumo = $pdo->prepare("SELECT 
    SUM(CASE WHEN contatipo = 'Entrada' THEN contavalor ELSE 0 END) as receitas,
    SUM(CASE WHEN contatipo = 'Saída' THEN contavalor ELSE 0 END) as despesas
    FROM contas WHERE usuarioid = ? AND contacompetencia = ?");
$stmt_resumo->execute([$uid, $mes_filtro]);
$resumo = $stmt_resumo->fetch();

$receitas = $resumo['receitas'] ?? 0;
$despesas = $resumo['despesas'] ?? 0;
$economizado = $receitas > 0 ? (($receitas - $despesas) / $receitas) * 100 : 0;
?>

<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="index.php" class="text-dark fs-4 me-3"><i class="bi bi-chevron-left"></i></a>
        <h4 class="fw-bold mb-0">Análise Mensal</h4>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6">
            <div class="card-stat">
                <div class="stat-icon bg-primary-subtle text-primary">
                    <i class="bi bi-piggy-bank"></i>
                </div>
                <small class="text-muted fw-bold d-block">ECONOMIA</small>
                <h5 class="fw-bold mb-0"><?= number_format(max(0, $economizado), 1) ?>%</h5>
            </div>
        </div>
        <div class="col-6">
            <div class="card-stat">
                <div class="stat-icon bg-warning-subtle text-warning">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <small class="text-muted fw-bold d-block">GASTO MÉDIO</small>
                <h5 class="fw-bold mb-0">R$ <?= number_format($despesas / (date('t')), 2, ',', '.') ?></h5>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
        <h6 class="fw-bold mb-4">Despesas por Categoria</h6>
        <?php if(empty($valores_cat)): ?>
            <p class="text-center text-muted py-4 small">Sem dados para este mês.</p>
        <?php else: ?>
            <canvas id="chartCategorias" style="max-height: 250px;"></canvas>
        <?php endif; ?>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
        <h6 class="fw-bold mb-4">Receitas vs Despesas</h6>
        <canvas id="chartComparativo" style="max-height: 200px;"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Gráfico de Categorias (Donut)
    const ctxCat = document.getElementById('chartCategorias')?.getContext('2d');
    if(ctxCat) {
        new Chart(ctxCat, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($labels_cat) ?>,
                datasets: [{
                    data: <?= json_encode($valores_cat) ?>,
                    backgroundColor: ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } } },
                cutout: '70%'
            }
        });
    }

    // Gráfico Comparativo (Barras)
    const ctxComp = document.getElementById('chartComparativo')?.getContext('2d');
    if(ctxComp) {
        new Chart(ctxComp, {
            type: 'bar',
            data: {
                labels: ['Entradas', 'Saídas'],
                datasets: [{
                    label: 'R$',
                    data: [<?= $receitas ?>, <?= $despesas ?>],
                    backgroundColor: ['#10b981', '#ef4444'],
                    borderRadius: 8
                }]
            },
            options: {
                scales: { y: { beginAtZero: true, grid: { display: false } }, x: { grid: { display: false } } },
                plugins: { legend: { display: false } }
            }
        });
    }
</script>

<?php require_once "../includes/footer.php"; ?>