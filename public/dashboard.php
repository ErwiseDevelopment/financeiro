<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');

// 1. BUSCA TOTAIS DO MÊS ATUAL
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
$saldo_previsto = $e_total - $s_total;
$saldo_real = ($res['e_paga'] ?? 0) - ($res['s_paga'] ?? 0);
$taxa_poupanca = ($e_total > 0) ? (($saldo_previsto) / $e_total) * 100 : 0;

// 2. BUSCA GASTOS POR MÉTODO
$stmt_metodos = $pdo->prepare("SELECT 
    SUM(CASE WHEN cartoid IS NOT NULL THEN contavalor ELSE 0 END) as total_cartao,
    SUM(CASE WHEN cartoid IS NULL THEN contavalor ELSE 0 END) as total_dinheiro
    FROM contas WHERE usuarioid = ? AND contacompetencia = ? AND contatipo = 'Saída'");
$stmt_metodos->execute([$uid, $mes_filtro]);
$metodos = $stmt_metodos->fetch();

$v_cartao = $metodos['total_cartao'] ?? 0;
$v_dinheiro = $metodos['total_dinheiro'] ?? 0;

// 3. TENDÊNCIA ÚLTIMOS 6 MESES
$meses_hist = []; $valores_hist_e = []; $valores_hist_s = [];
for($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months", strtotime(date('Y-m-01'))));
    $stmt_h = $pdo->prepare("SELECT 
        SUM(CASE WHEN contatipo = 'Entrada' THEN contavalor ELSE 0 END) as e,
        SUM(CASE WHEN contatipo = 'Saída' THEN contavalor ELSE 0 END) as s
        FROM contas WHERE usuarioid = ? AND contacompetencia = ?");
    $stmt_h->execute([$uid, $m]);
    $h = $stmt_h->fetch();
    $meses_hist[] = date('M', strtotime($m."-01"));
    $valores_hist_e[] = $h['e'] ?? 0;
    $valores_hist_s[] = $h['s'] ?? 0;
}

// 4. RANKING DE CATEGORIAS
$stmt_cat = $pdo->prepare("SELECT cat.categoriadescricao as label, SUM(c.contavalor) as total 
    FROM contas c 
    JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contacompetencia = ? AND c.contatipo = 'Saída'
    GROUP BY cat.categoriadescricao ORDER BY total DESC LIMIT 6");
$stmt_cat->execute([$uid, $mes_filtro]);
$dados_cat = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    :root { 
        --primary: #4361ee; --success: #2ec4b6; --danger: #e71d36; --dark: #1e293b; 
    }
    body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }

    /* Barra de Meses Responsiva */
    .nav-months {
        display: flex;
        overflow-x: auto;
        gap: 8px;
        padding-bottom: 10px;
        scrollbar-width: none; /* Firefox */
    }
    .nav-months::-webkit-scrollbar { display: none; } /* Chrome/Safari */
    
    .btn-month {
        padding: 10px 20px;
        border-radius: 50px;
        background: #fff;
        border: 1px solid #e2e8f0;
        color: #64748b;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
        transition: 0.3s;
        font-size: 0.85rem;
    }
    .btn-month.active {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
    }

    /* Cards e UI */
    .card-stat { border: none; border-radius: 20px; background: #fff; box-shadow: 0 2px 15px rgba(0,0,0,0.04); }
    .glass-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 12px; }
    
    .progress-container { background: #f1f5f9; height: 8px; border-radius: 10px; width: 100%; overflow: hidden; }
    .progress-bar-fill { height: 100%; border-radius: 10px; transition: width 0.8s ease; }
    
    @media (max-width: 768px) {
        .chart-container { height: 220px !important; }
        h4 { font-size: 1.2rem; }
    }
</style>

<div class="container py-4">
    
    <div class="mb-4">
        <h4 class="fw-bold mb-3">Painel de Controle</h4>
        
        <div class="nav-months">
            <?php 
            for($i = -4; $i <= 4; $i++): 
                $m = date('Y-m', strtotime("+$i month", strtotime(date('Y-m-01'))));
                $active = ($m == $mes_filtro) ? 'active' : '';
                $label = new IntlDateFormatter('pt_BR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'MMM yy');
            ?>
                <a href="?mes=<?= $m ?>" class="btn-month <?= $active ?>">
                    <?= ucfirst($label->format(strtotime($m."-01"))) ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card-stat p-3 h-100">
                <div class="glass-icon bg-success text-white"><i class="bi bi-graph-up"></i></div>
                <small class="text-muted d-block">Receitas</small>
                <span class="fw-bold text-dark">R$ <?= number_format($e_total, 2, ',', '.') ?></span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-stat p-3 h-100">
                <div class="glass-icon bg-danger text-white"><i class="bi bi-graph-down"></i></div>
                <small class="text-muted d-block">Despesas</small>
                <span class="fw-bold text-dark">R$ <?= number_format($s_total, 2, ',', '.') ?></span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-stat p-3 h-100">
                <div class="glass-icon bg-primary text-white"><i class="bi bi-credit-card"></i></div>
                <small class="text-muted d-block">Cartão</small>
                <span class="fw-bold text-dark">R$ <?= number_format($v_cartao, 2, ',', '.') ?></span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-stat p-3 h-100">
                <div class="glass-icon <?= $saldo_previsto >= 0 ? 'bg-info' : 'bg-warning' ?> text-white"><i class="bi bi-wallet2"></i></div>
                <small class="text-muted d-block">Saldo</small>
                <span class="fw-bold text-dark">R$ <?= number_format($saldo_previsto, 2, ',', '.') ?></span>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-8">
            <div class="card-stat p-4 h-100">
                <h6 class="fw-bold mb-4 small text-uppercase text-muted">Histórico Recente</h6>
                <div style="height: 300px;">
                    <canvas id="chartEvolucao"></canvas>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card-stat p-4 h-100">
                <h6 class="fw-bold mb-4 small text-uppercase text-muted">Método de Pagamento</h6>
                <div style="height: 220px;">
                    <canvas id="chartMeios"></canvas>
                </div>
                <div class="mt-4 p-3 rounded-4 bg-light text-center">
                    <small class="text-muted d-block">Uso de Crédito</small>
                    <h5 class="fw-bold mb-0 text-primary"><?= ($s_total > 0) ? round(($v_cartao / $s_total) * 100) : 0 ?>%</h5>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <div class="card-stat p-4 h-100">
                <h6 class="fw-bold mb-4 small text-uppercase text-muted">Distribuição por Categoria</h6>
                <?php if(empty($dados_cat)): ?>
                    <p class="text-center text-muted py-5">Sem registros neste mês.</p>
                <?php else: foreach($dados_cat as $c): 
                    $p = ($s_total > 0) ? ($c['total'] / $s_total) * 100 : 0;
                    $cor = ($p > 30) ? 'var(--danger)' : (($p > 15) ? 'var(--primary)' : 'var(--success)');
                ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small fw-bold"><?= $c['label'] ?></span>
                            <span class="small text-muted">R$ <?= number_format($c['total'], 2, ',', '.') ?></span>
                        </div>
                        <div class="progress-container">
                            <div class="progress-bar-fill" style="width: <?= $p ?>%; background-color: <?= $cor ?>;"></div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card-stat p-4 h-100">
                <h6 class="fw-bold mb-4 small text-uppercase text-muted">Insights do Mês</h6>
                <div class="row g-3">
                    <div class="col-12 col-sm-6">
                        <div class="p-4 border rounded-4 text-center">
                            <h3 class="fw-bold <?= $taxa_poupanca > 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($taxa_poupanca, 1) ?>%</h3>
                            <small class="text-muted fw-bold">Margem de Lucro</small>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="p-4 border rounded-4 text-center">
                            <h3 class="fw-bold text-dark">R$ <?= number_format($saldo_real, 2, ',', '.') ?></h3>
                            <small class="text-muted fw-bold">Liquidez em Caixa</small>
                        </div>
                    </div>
                </div>
                <div class="mt-4 p-3 bg-primary bg-opacity-10 border-start border-primary border-4 rounded-end">
                    <p class="small mb-0 text-dark">
                        <i class="bi bi-lightbulb-fill text-primary me-2"></i>
                        Seu custo de vida diário médio é de <strong>R$ <?= number_format($s_total / 30, 2, ',', '.') ?></strong>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Configurações Globais dos Gráficos
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#94a3b8';

    // Gráfico de Histórico
    new Chart(document.getElementById('chartEvolucao'), {
        type: 'line',
        data: {
            labels: <?= json_encode($meses_hist) ?>,
            datasets: [
                { label: 'Entradas', data: <?= json_encode($valores_hist_e) ?>, borderColor: '#2ec4b6', backgroundColor: '#2ec4b622', fill: true, tension: 0.4 },
                { label: 'Saídas', data: <?= json_encode($valores_hist_s) ?>, borderColor: '#e71d36', backgroundColor: '#e71d3611', fill: true, tension: 0.4 }
            ]
        },
        options: { 
            maintainAspectRatio: false, 
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { grid: { borderDash: [5,5] } }, x: { grid: { display: false } } }
        }
    });

    // Gráfico de Meios
    new Chart(document.getElementById('chartMeios'), {
        type: 'doughnut',
        data: {
            labels: ['Dinheiro', 'Cartão'],
            datasets: [{
                data: [<?= $v_dinheiro ?>, <?= $v_cartao ?>],
                backgroundColor: [ '#4361ee', '#7209b7' ],
                borderWidth: 0,
                cutout: '85%'
            }]
        },
        options: { 
            maintainAspectRatio: false, 
            plugins: { legend: { position: 'bottom' } } 
        }
    });
</script>

<?php require_once "../includes/footer.php"; ?>