<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');

// 1. BALANÇO MENSAL ATUAL
$stmt_resumo = $pdo->prepare("SELECT 
    SUM(CASE WHEN contatipo = 'Entrada' THEN contavalor ELSE 0 END) as receitas,
    SUM(CASE WHEN contatipo = 'Saída' THEN contavalor ELSE 0 END) as despesas
    FROM contas WHERE usuarioid = ? AND contacompetencia = ?");
$stmt_resumo->execute([$uid, $mes_filtro]);
$resumo = $stmt_resumo->fetch();

$receitas = $resumo['receitas'] ?? 0;
$despesas = $resumo['despesas'] ?? 0;
$saldo_atual = $receitas - $despesas;

// 2. TENDÊNCIA VS MÊS ANTERIOR
$mes_ant = date('Y-m', strtotime("-1 month", strtotime($mes_filtro . "-01")));
$stmt_ant = $pdo->prepare("SELECT SUM(contavalor) as total FROM contas 
    WHERE usuarioid = ? AND contacompetencia = ? AND contatipo = 'Saída'");
$stmt_ant->execute([$uid, $mes_ant]);
$total_mes_anterior = $stmt_ant->fetch()['total'] ?? 0;

$diff_percentual = 0;
if ($total_mes_anterior > 0) {
    $diff_percentual = (($despesas - $total_mes_anterior) / $total_mes_anterior) * 100;
}

// 3. TOTAIS POR CATEGORIA (PARA O GRÁFICO E INSIGHT)
$stmt_cat = $pdo->prepare("SELECT cat.categoriadescricao, SUM(c.contavalor) as total 
    FROM contas c 
    JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contacompetencia = ? AND c.contatipo = 'Saída'
    GROUP BY cat.categoriadescricao ORDER BY total DESC");
$stmt_cat->execute([$uid, $mes_filtro]);
$dados_categorias = $stmt_cat->fetchAll();

$labels_cat = array_column($dados_categorias, 'categoriadescricao');
$valores_cat = array_column($dados_categorias, 'total');
$maior_gasto_cat = $labels_cat[0] ?? 'Nenhuma';

// 4. LÓGICA DE SAÚDE FINANCEIRA
$percentual_gasto = $receitas > 0 ? ($despesas / $receitas) * 100 : 0;
$cor_saude = "bg-success";
if($percentual_gasto > 50) $cor_saude = "bg-warning text-dark";
if($percentual_gasto > 80) $cor_saude = "bg-danger";
?>

<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="index.php" class="text-dark fs-4 me-3"><i class="bi bi-chevron-left"></i></a>
        <h4 class="fw-bold mb-0">Inteligência Financeira</h4>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 <?= $saldo_atual >= 0 ? 'bg-primary-subtle' : 'bg-danger-subtle' ?>">
        <div class="d-flex align-items-center">
            <div class="fs-1 me-3">
                <?= $saldo_atual >= 0 ? '🚀' : '⚠️' ?>
            </div>
            <div>
                <h6 class="fw-bold mb-1 text-dark">Dica do FinancePro</h6>
                <p class="small mb-0 text-dark opacity-75">
                    <?php if($saldo_atual >= 0): ?>
                        Você tem <strong>R$ <?= number_format($saldo_atual, 2, ',', '.') ?></strong> sobrando este mês. 
                        Que tal investir 10% (R$ <?= number_format($saldo_atual*0.1, 2, ',', '.') ?>) hoje mesmo?
                    <?php else: ?>
                        Atenção: Suas saídas superaram as entradas. Revise seus gastos em <strong>"<?= $maior_gasto_cat ?>"</strong> para equilibrar as contas.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <small class="text-muted fw-bold d-block small mb-2">GASTOS VS MÊS ANTERIOR</small>
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <?php if($diff_percentual > 0): ?>
                            <span class="badge bg-danger-subtle text-danger rounded-pill p-2 px-3">
                                <i class="bi bi-graph-up-arrow me-1"></i> +<?= number_format($diff_percentual, 1) ?>%
                            </span>
                        <?php else: ?>
                            <span class="badge bg-success-subtle text-success rounded-pill p-2 px-3">
                                <i class="bi bi-graph-down-arrow me-1"></i> <?= number_format($diff_percentual, 1) ?>%
                            </span>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted">Você gastou <?= $diff_percentual > 0 ? 'mais' : 'menos' ?> que em <?= date('M', strtotime($mes_ant."-01")) ?></small>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex justify-content-between align-items-end mb-2">
                    <small class="text-muted fw-bold small">COMPROMETIMENTO DA RENDA</small>
                    <span class="fw-bold small"><?= number_format($percentual_gasto, 1) ?>%</span>
                </div>
                <div class="progress" style="height: 12px; border-radius: 20px;">
                    <div class="progress-bar <?= $cor_saude ?>" role="progressbar" style="width: <?= min(100, $percentual_gasto) ?>%"></div>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <small style="font-size: 0.6rem;" class="text-muted">0%</small>
                    <small style="font-size: 0.6rem;" class="text-muted text-uppercase fw-bold">Meta: abaixo de 80%</small>
                    <small style="font-size: 0.6rem;" class="text-muted">100%</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
        <h6 class="fw-bold mb-4">Gastos por Categoria</h6>
        <canvas id="chartCategorias" style="max-height: 250px;"></canvas>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
        <h6 class="fw-bold mb-4">Comparativo Entradas/Saídas</h6>
        <canvas id="chartComparativo" style="max-height: 200px;"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Configurações do Chart.js permanecem as mesmas que as anteriores...
    const ctxCat = document.getElementById('chartCategorias')?.getContext('2d');
    if(ctxCat) {
        new Chart(ctxCat, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($labels_cat) ?>,
                datasets: [{
                    data: <?= json_encode($valores_cat) ?>,
                    backgroundColor: ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'],
                    borderWidth: 0
                }]
            },
            options: {
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } } },
                cutout: '75%'
            }
        });
    }

    const ctxComp = document.getElementById('chartComparativo')?.getContext('2d');
    if(ctxComp) {
        new Chart(ctxComp, {
            type: 'bar',
            data: {
                labels: ['Entradas', 'Saídas'],
                datasets: [{
                    data: [<?= $receitas ?>, <?= $despesas ?>],
                    backgroundColor: ['#10b981', '#ef4444'],
                    borderRadius: 10
                }]
            },
            options: {
                scales: { y: { display: false }, x: { grid: { display: false } } },
                plugins: { legend: { display: false } }
            }
        });
    }
</script>

<?php require_once "../includes/footer.php"; ?>