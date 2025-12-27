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

$fmt_mes = new IntlDateFormatter('pt_BR', 0, 0, null, null, 'MMMM yyyy');
$titulo_mes = ucfirst($fmt_mes->format(strtotime($mes_filtro."-01")));
?>

<style>
    :root { 
        --primary: #4361ee; --success: #10b981; --danger: #ef4444; --dark: #0f172a; 
    }
    body { background-color: #f8fafc; font-family: 'Inter', sans-serif; color: #334155; }

    /* Barra de Meses Modernizada */
    .nav-months { display: flex; overflow-x: auto; gap: 10px; padding: 5px 0 15px 0; scrollbar-width: none; }
    .nav-months::-webkit-scrollbar { display: none; }
    
    .btn-month {
        padding: 10px 22px; border-radius: 16px; background: #fff; border: 1px solid #e2e8f0;
        color: #64748b; font-weight: 600; text-decoration: none; white-space: nowrap; transition: 0.3s; font-size: 0.85rem;
    }
    .btn-month.active {
        background: var(--primary); color: #fff; border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(67, 97, 238, 0.25);
    }

    /* Cards e UI */
    .card-stat { border: none; border-radius: 24px; background: #fff; box-shadow: 0 4px 20px rgba(0,0,0,0.03); transition: transform 0.3s ease; }
    .card-stat:hover { transform: translateY(-3px); }
    
    .glass-icon { 
        width: 48px; height: 48px; border-radius: 16px; display: flex; 
        align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 15px; 
    }
    
    .progress-container { background: #f1f5f9; height: 8px; border-radius: 10px; width: 100%; overflow: hidden; }
    .progress-bar-fill { height: 100%; border-radius: 10px; transition: width 1s ease; }
    
    .insight-box {
        background: #fff; border-radius: 20px; border: 1px solid #f1f5f9;
        transition: all 0.3s ease;
    }

    @media (max-width: 768px) {
        .chart-container { height: 250px !important; }
        .display-amount { font-size: 1.1rem; }
    }
</style>

<div class="container py-4">
    
    <div class="mb-4 px-2">
        <h4 class="fw-bold mb-1">Dashboard Financeiro</h4>
        <p class="text-muted small"><?= $titulo_mes ?></p>
        
        <div class="nav-months mt-3">
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
                <div class="glass-icon bg-success bg-opacity-10 text-success"><i class="bi bi-arrow-up-right"></i></div>
                <small class="text-muted d-block fw-bold" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px;">Receitas</small>
                <span class="fw-bold text-dark display-amount">R$ <?= number_format($e_total, 2, ',', '.') ?></span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-stat p-3 h-100">
                <div class="glass-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-arrow-down-left"></i></div>
                <small class="text-muted d-block fw-bold" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px;">Despesas</small>
                <span class="fw-bold text-dark display-amount">R$ <?= number_format($s_total, 2, ',', '.') ?></span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-stat p-3 h-100">
                <div class="glass-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-credit-card-2-front"></i></div>
                <small class="text-muted d-block fw-bold" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px;">No Cartão</small>
                <span class="fw-bold text-dark display-amount">R$ <?= number_format($v_cartao, 2, ',', '.') ?></span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-stat p-3 h-100">
                <div class="glass-icon <?= $saldo_previsto >= 0 ? 'bg-info' : 'bg-warning' ?> bg-opacity-10 <?= $saldo_previsto >= 0 ? 'text-info' : 'text-warning' ?>"><i class="bi bi-wallet2"></i></div>
                <small class="text-muted d-block fw-bold" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px;">Saldo Final</small>
                <span class="fw-bold text-dark display-amount">R$ <?= number_format($saldo_previsto, 2, ',', '.') ?></span>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-8">
            <div class="card-stat p-4 h-100">
                <h6 class="fw-bold mb-4 small text-uppercase text-muted" style="letter-spacing: 1px;">Evolução Mensal</h6>
                <div class="chart-container" style="height: 300px;">
                    <canvas id="chartEvolucao"></canvas>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card-stat p-4 h-100 text-center">
                <h6 class="fw-bold mb-4 small text-uppercase text-muted text-start" style="letter-spacing: 1px;">Meios de Pagamento</h6>
                <div class="chart-container" style="height: 220px;">
                    <canvas id="chartMeios"></canvas>
                </div>
                <div class="mt-4 p-3 rounded-4 bg-light">
                    <small class="text-muted d-block mb-1 fw-bold" style="font-size: 0.7rem;">DEPENDÊNCIA DE CRÉDITO</small>
                    <h4 class="fw-bold mb-0 text-primary"><?= ($s_total > 0) ? round(($v_cartao / $s_total) * 100) : 0 ?>%</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <div class="card-stat p-4 h-100">
                <h6 class="fw-bold mb-4 small text-uppercase text-muted" style="letter-spacing: 1px;">Gastos por Categoria</h6>
                <?php if(empty($dados_cat)): ?>
                    <div class="text-center py-5 text-muted small">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                        Sem dados para exibir
                    </div>
                <?php else: foreach($dados_cat as $c): 
                    $p = ($s_total > 0) ? ($c['total'] / $s_total) * 100 : 0;
                    $cor = ($p > 30) ? 'var(--danger)' : (($p > 15) ? 'var(--primary)' : 'var(--success)');
                ?>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="small fw-bold text-dark"><?= $c['label'] ?></span>
                            <span class="small text-muted fw-bold">R$ <?= number_format($c['total'], 2, ',', '.') ?></span>
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
                <h6 class="fw-bold mb-4 small text-uppercase text-muted" style="letter-spacing: 1px;">Resumo de Eficiência</h6>
                <div class="row g-3">
                    <div class="col-12 col-sm-6">
                        <div class="insight-box p-4 text-center h-100">
                            <h3 class="fw-bold mb-1 <?= $taxa_poupanca > 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($taxa_poupanca, 1) ?>%</h3>
                            <small class="text-muted fw-bold" style="font-size: 0.65rem;">MARGEM LÍQUIDA</small>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="insight-box p-4 text-center h-100">
                            <h3 class="fw-bold text-dark mb-1">R$ <?= number_format($saldo_real, 2, ',', '.') ?></h3>
                            <small class="text-muted fw-bold" style="font-size: 0.65rem;">DISPONÍVEL AGORA</small>
                        </div>
                    </div>
                </div>
                <div class="mt-4 p-4 bg-primary bg-opacity-10 border-0 rounded-4 position-relative overflow-hidden">
                    <div class="position-absolute end-0 bottom-0 opacity-10 me-n3 mb-n3">
                        <i class="bi bi-lightbulb" style="font-size: 5rem;"></i>
                    </div>
                    <h6 class="fw-bold text-primary mb-2 small"><i class="bi bi-stars me-2"></i>Dica do Mês</h6>
                    <p class="small mb-0 text-dark opacity-75" style="line-height: 1.6;">
                        Seu custo de vida médio é de <strong>R$ <?= number_format($s_total / 30, 2, ',', '.') ?> por dia</strong>. 
                        <?php if($taxa_poupanca > 20): ?> Excelente! Você está guardando uma ótima fatia da sua renda. <?php else: ?> Tente reduzir gastos variáveis para aumentar sua margem. <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#94a3b8';

    // Gráfico de Evolução (Linha)
    new Chart(document.getElementById('chartEvolucao'), {
        type: 'line',
        data: {
            labels: <?= json_encode($meses_hist) ?>,
            datasets: [
                { 
                    label: 'Entradas', 
                    data: <?= json_encode($valores_hist_e) ?>, 
                    borderColor: '#10b981', 
                    backgroundColor: 'rgba(16, 185, 129, 0.1)', 
                    fill: true, 
                    tension: 0.4,
                    borderWidth: 3,
                    pointRadius: 4
                },
                { 
                    label: 'Saídas', 
                    data: <?= json_encode($valores_hist_s) ?>, 
                    borderColor: '#ef4444', 
                    backgroundColor: 'rgba(239, 68, 68, 0.05)', 
                    fill: true, 
                    tension: 0.4,
                    borderWidth: 3,
                    pointRadius: 4
                }
            ]
        },
        options: { 
            maintainAspectRatio: false, 
            plugins: { legend: { display: true, position: 'bottom', labels: { usePointStyle: true, padding: 20 } } },
            scales: { 
                y: { grid: { borderDash: [5,5], color: '#e2e8f0' }, border: { display: false } }, 
                x: { grid: { display: false } } 
            }
        }
    });

    // Gráfico de Meios (Donut)
    new Chart(document.getElementById('chartMeios'), {
        type: 'doughnut',
        data: {
            labels: ['Débito/Dinheiro', 'Cartão de Crédito'],
            datasets: [{
                data: [<?= $v_dinheiro ?>, <?= $v_cartao ?>],
                backgroundColor: [ '#4361ee', '#8b5cf6' ],
                hoverOffset: 10,
                borderWidth: 0,
                cutout: '82%'
            }]
        },
        options: { 
            maintainAspectRatio: false, 
            plugins: { 
                legend: { display: true, position: 'bottom', labels: { usePointStyle: true, padding: 20 } } 
            } 
        }
    });
</script>

<?php require_once "../includes/footer.php"; ?>