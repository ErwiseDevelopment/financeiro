<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');

// --- 1. SALDO ACUMULADO (SÓ O QUE FOI PAGO/RECEBIDO NO PASSADO) ---
$stmt_passado = $pdo->prepare("SELECT 
    SUM(CASE WHEN contatipo = 'Entrada' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) -
    SUM(CASE WHEN contatipo = 'Saída' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) as saldo_acumulado
    FROM contas WHERE usuarioid = ? AND contacompetencia < ?");
$stmt_passado->execute([$uid, $mes_filtro]);
$saldo_anterior = $stmt_passado->fetch()['saldo_acumulado'] ?? 0;

// --- 2. BUSCA TOTAIS DO MÊS FILTRADO ---
$stmt_totais = $pdo->prepare("SELECT 
    SUM(CASE WHEN contatipo = 'Entrada' THEN contavalor ELSE 0 END) as e_total,
    SUM(CASE WHEN contatipo = 'Saída' THEN contavalor ELSE 0 END) as s_total,
    SUM(CASE WHEN contatipo = 'Entrada' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) as e_paga,
    SUM(CASE WHEN contatipo = 'Saída' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) as s_paga,
    SUM(CASE WHEN contatipo = 'Saída' AND contasituacao = 'Pendente' THEN contavalor ELSE 0 END) as s_pendente
    FROM contas WHERE usuarioid = ? AND contacompetencia = ?");
$stmt_totais->execute([$uid, $mes_filtro]);
$res = $stmt_totais->fetch();

$e_total = abs($res['e_total'] ?? 0);
$s_total = abs($res['s_total'] ?? 0);
$e_paga  = abs($res['e_paga'] ?? 0);
$s_paga  = abs($res['s_paga'] ?? 0);
$despesa_pendente = abs($res['s_pendente'] ?? 0);

// SALDO REAL (O que tem no bolso agora)
$saldo_real = $saldo_anterior + ($e_paga - $s_paga);

// RESUMO DO MÊS (Projeção final considerando o passado)
$resumo_mes_final = $saldo_anterior + ($e_total - $s_total);

// Margem Líquida do Mês (Eficiência)
$saldo_previsto_mes = $e_total - $s_total;
$taxa_poupanca = ($e_total > 0) ? ($saldo_previsto_mes / $e_total) * 100 : 0;

// --- 3. ANÁLISE DE CARTÃO (GLOBAL) ---
$stmt_cartoes = $pdo->prepare("SELECT SUM(cartolimite) as limite_total,
    (SELECT SUM(contavalor) FROM contas WHERE usuarioid = ? AND cartoid IS NOT NULL AND contasituacao = 'Pendente') as total_comprometido
    FROM cartoes WHERE usuarioid = ?");
$stmt_cartoes->execute([$uid, $uid]);
$infocartao = $stmt_cartoes->fetch();
$limite_total = $infocartao['limite_total'] ?? 0;
$total_preso = abs($infocartao['total_comprometido'] ?? 0);
$limite_livre = $limite_total - $total_preso;
$perc_limite = ($limite_total > 0) ? ($total_preso / $limite_total) * 100 : 0;

// --- 4. MEIOS DE PAGAMENTO (COMPETÊNCIA) ---
$stmt_metodos = $pdo->prepare("SELECT 
    SUM(CASE WHEN cartoid IS NOT NULL THEN contavalor ELSE 0 END) as total_cartao,
    SUM(CASE WHEN cartoid IS NULL THEN contavalor ELSE 0 END) as total_dinheiro
    FROM contas WHERE usuarioid = ? AND contacompetencia = ? AND contatipo = 'Saída'");
$stmt_metodos->execute([$uid, $mes_filtro]);
$metodos = $stmt_metodos->fetch();
$v_cartao = abs($metodos['total_cartao'] ?? 0);
$v_dinheiro = abs($metodos['total_dinheiro'] ?? 0);

// --- 5. HISTÓRICO 6 MESES ---
$meses_hist = []; $valores_hist_e = []; $valores_hist_s = [];
for($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months", strtotime(date('Y-m-01'))));
    $stmt_h = $pdo->prepare("SELECT 
        SUM(CASE WHEN contatipo = 'Entrada' THEN contavalor ELSE 0 END) as e,
        SUM(CASE WHEN contatipo = 'Saída' THEN contavalor ELSE 0 END) as s
        FROM contas WHERE usuarioid = ? AND contacompetencia = ?");
    $stmt_h->execute([$uid, $m]);
    $h = $stmt_h->fetch();
    $meses_hist[] = ucfirst((new IntlDateFormatter('pt_BR', 0, 0, null, null, 'MMM')) ->format(strtotime($m."-01")));
    $valores_hist_e[] = abs($h['e'] ?? 0);
    $valores_hist_s[] = abs($h['s'] ?? 0);
}

// --- 6. CATEGORIAS E LANÇAMENTOS ---
$stmt_cat = $pdo->prepare("SELECT cat.categoriaid, cat.categoriadescricao as label, SUM(c.contavalor) as total 
    FROM contas c JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contacompetencia = ? AND c.contatipo = 'Saída'
    GROUP BY cat.categoriaid ORDER BY total DESC");
$stmt_cat->execute([$uid, $mes_filtro]);
$categorias_mes = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

$stmt_all = $pdo->prepare("SELECT c.*, cat.categoriadescricao, car.cartonome 
    FROM contas c LEFT JOIN categorias cat ON c.categoriaid = cat.categoriaid LEFT JOIN cartoes car ON c.cartoid = car.cartoid
    WHERE c.usuarioid = ? AND c.contacompetencia = ? ORDER BY c.contavencimento DESC");
$stmt_all->execute([$uid, $mes_filtro]);
$todos_lancamentos = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

$titulo_mes = ucfirst((new IntlDateFormatter('pt_BR', 0, 0, null, null, 'MMMM yyyy'))->format(strtotime($mes_filtro."-01")));
?>

<style>
    :root { --primary: #4361ee; --success: #10b981; --danger: #ef4444; --warning: #f59e0b; --info: #3a86ff; --dark: #1e293b; --purple: #7209b7; }
    body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
    .card-stat { border: none; border-radius: 20px; background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.02); transition: 0.3s; height: 100%; }
    .card-stat:hover { transform: translateY(-3px); }
    .icon-box { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
    .category-btn { cursor: pointer; padding: 15px; border-radius: 15px; border: 1px solid #f1f5f9; transition: 0.2s; background: #fff; margin-bottom: 10px; width: 100%; text-align: left; }
    .category-btn:hover, .category-btn.active { border-color: var(--primary); background: #f0f3ff; }
    .transaction-item { display: flex; align-items: center; padding: 12px; border-radius: 12px; margin-bottom: 8px; background: #fff; border: 1px solid #f1f5f9; }
    .hidden { display: none !important; }
    .progress-thin { height: 6px; border-radius: 10px; background: rgba(0,0,0,0.05); }
    .text-resumo { color: var(--purple); }
    .bg-purple-soft { background-color: #f3e8ff; border: 1px solid #e9d5ff; }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Dashboard Analítico</h4>
            <small class="text-muted"><?= $titulo_mes ?></small>
        </div>
        <div class="dropdown">
            <button class="btn btn-white shadow-sm rounded-pill px-4" data-bs-toggle="dropdown">
                <i class="bi bi-calendar3 me-2 text-primary"></i>Alterar Mês
            </button>
            <ul class="dropdown-menu shadow border-0">
                <?php for($i = -2; $i <= 4; $i++): $m = date('Y-m', strtotime("$i month")); ?>
                    <li><a class="dropdown-item" href="?mes=<?= $m ?>"><?= $m ?></a></li>
                <?php endfor; ?>
            </ul>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md">
            <div class="card-stat p-3">
                <div class="icon-box bg-success bg-opacity-10 text-success mb-2"><i class="bi bi-arrow-up-circle"></i></div>
                <small class="text-muted fw-bold d-block mb-1" style="font-size: 0.6rem;">ENTRADAS (TOTAL)</small>
                <h6 class="fw-bold text-dark mb-0">R$ <?= number_format($e_total, 2, ',', '.') ?></h6>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card-stat p-3">
                <div class="icon-box bg-danger bg-opacity-10 text-danger mb-2"><i class="bi bi-arrow-down-circle"></i></div>
                <small class="text-muted fw-bold d-block mb-1" style="font-size: 0.6rem;">SAÍDAS (TOTAL)</small>
                <h6 class="fw-bold text-dark mb-0">R$ <?= number_format($s_total, 2, ',', '.') ?></h6>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card-stat p-3">
                <div class="icon-box bg-warning bg-opacity-10 text-warning mb-2"><i class="bi bi-clock-history"></i></div>
                <small class="text-muted fw-bold d-block mb-1" style="font-size: 0.6rem;">PENDENTE MÊS</small>
                <h6 class="fw-bold text-dark mb-0">R$ <?= number_format($despesa_pendente, 2, ',', '.') ?></h6>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card-stat p-3">
                <div class="icon-box bg-info bg-opacity-10 text-info mb-2"><i class="bi bi-wallet2"></i></div>
                <small class="text-muted fw-bold d-block mb-1" style="font-size: 0.6rem;">SALDO REAL</small>
                <h6 class="fw-bold text-dark mb-0">R$ <?= number_format($saldo_real, 2, ',', '.') ?></h6>
            </div>
        </div>
        <div class="col-12 col-md">
            <div class="card-stat p-3 bg-purple-soft">
                <div class="icon-box bg-purple text-white mb-2" style="background-color: var(--purple);"><i class="bi bi-calculator"></i></div>
                <small class="text-muted fw-bold d-block mb-1" style="font-size: 0.6rem;">PROJEÇÃO FINAL</small>
                <h6 class="fw-bold text-resumo mb-0">R$ <?= number_format($resumo_mes_final, 2, ',', '.') ?></h6>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-7">
            <div class="card-stat p-4 bg-dark text-white">
                <h6 class="fw-bold mb-4 small text-uppercase opacity-75">Saúde do Crédito (Global)</h6>
                <div class="row align-items-center">
                    <div class="col-md-6 border-end border-secondary">
                        <small class="opacity-50 d-block mb-1">Limite Comprometido</small>
                        <h2 class="fw-bold mb-2">R$ <?= number_format($total_preso, 2, ',', '.') ?></h2>
                        <div class="progress mb-2" style="height: 6px; background: rgba(255,255,255,0.1);">
                            <div class="progress-bar bg-info" style="width: <?= $perc_limite ?>%"></div>
                        </div>
                        <small class="text-info"><?= number_format($perc_limite, 1) ?>% em uso</small>
                    </div>
                    <div class="col-md-6 ps-md-4">
                        <small class="opacity-50 d-block mb-1">Livre para Compras</small>
                        <h3 class="fw-bold text-success mb-2">R$ <?= number_format($limite_livre, 2, ',', '.') ?></h3>
                        <small class="opacity-50 text-uppercase" style="font-size: 0.6rem;">Total: R$ <?= number_format($limite_total, 2, ',', '.') ?></small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-5">
            <div class="card-stat p-4">
                <h6 class="fw-bold mb-4 small text-uppercase text-muted">Eficiência do Mês</h6>
                <div class="d-flex align-items-center gap-4">
                    <div style="width: 110px; height: 110px;"><canvas id="chartMeios"></canvas></div>
                    <div>
                        <h4 class="fw-bold mb-0 text-primary"><?= number_format($taxa_poupanca, 1) ?>%</h4>
                        <small class="text-muted">margem líquida prevista</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card-stat p-4 mb-4">
        <h6 class="fw-bold mb-4 small text-uppercase text-muted">Tendência de Fluxo de Caixa (Competência)</h6>
        <div style="height: 250px;"><canvas id="chartEvolucao"></canvas></div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-4">
            <div class="card-stat p-4">
                <h6 class="fw-bold mb-4 small text-uppercase text-muted">Categorias de Saída</h6>
                <?php foreach($categorias_mes as $c): $p = ($s_total > 0) ? ($c['total'] / $s_total) * 100 : 0; ?>
                <button class="category-btn" onclick="filterCategory(<?= $c['categoriaid'] ?>, this)">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-bold text-uppercase" style="font-size: 0.65rem;"><?= $c['label'] ?></span>
                        <span class="small fw-bold">R$ <?= number_format(abs($c['total']), 2, ',', '.') ?></span>
                    </div>
                    <div class="progress progress-thin"><div class="progress-bar bg-primary" style="width: <?= $p ?>%"></div></div>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-12 col-lg-8">
            <div class="card-stat p-4">
                <h6 class="fw-bold mb-4 small text-uppercase text-muted">Extrato Mensal</h6>
                <div id="extrato-container">
                    <?php foreach($todos_lancamentos as $l): 
                        $is_saida = ($l['contatipo'] == 'Saída'); 
                        $pago = ($l['contasituacao'] == 'Pago');
                    ?>
                    <div class="transaction-item js-item" data-catid="<?= $l['categoriaid'] ?>">
                        <div class="flex-grow-1">
                            <h6 class="mb-0 fw-bold <?= $pago ? 'text-muted text-decoration-line-through' : '' ?>" style="font-size: 0.8rem;"><?= $l['contadescricao'] ?></h6>
                            <small class="text-muted" style="font-size: 0.7rem;">
                                <?= date('d/m', strtotime($l['contavencimento'])) ?> • <?= $l['categoriadescricao'] ?> 
                                <?= $l['cartonome'] ? " • <span class='text-warning'>{$l['cartonome']}</span>" : "" ?>
                            </small>
                        </div>
                        <div class="text-end">
                            <span class="fw-bold d-block <?= $is_saida ? 'text-dark' : 'text-success' ?>" style="font-size: 0.8rem;">
                                <?= $is_saida ? '-' : '+' ?> R$ <?= number_format(abs($l['contavalor']), 2, ',', '.') ?>
                            </span>
                            <span class="badge <?= $pago ? 'bg-success' : 'bg-light text-dark' ?>" style="font-size: 0.5rem;"><?= $pago ? 'PAGO' : 'PENDENTE' ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    function filterCategory(catId, btn) {
        document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.js-item').forEach(i => {
            i.classList.toggle('hidden', catId !== 'all' && i.dataset.catid != catId);
        });
    }

    new Chart(document.getElementById('chartEvolucao'), {
        type: 'line',
        data: {
            labels: <?= json_encode($meses_hist) ?>,
            datasets: [
                { label: 'Entradas', data: <?= json_encode($valores_hist_e) ?>, borderColor: '#10b981', tension: 0.4, fill: false },
                { label: 'Saídas', data: <?= json_encode($valores_hist_s) ?>, borderColor: '#ef4444', tension: 0.4, fill: false }
            ]
        },
        options: { 
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: true } }
        }
    });

    new Chart(document.getElementById('chartMeios'), {
        type: 'doughnut',
        data: {
            labels: ['Dinheiro', 'Cartão'],
            datasets: [{ 
                data: [<?= $v_dinheiro ?>, <?= $v_cartao ?>], 
                backgroundColor: ['#4361ee', '#f59e0b'],
                borderWidth: 0
            }]
        },
        options: { maintainAspectRatio: false, cutout: '80%', plugins: { legend: { display: false } } }
    });
</script>

<?php require_once "../includes/footer.php"; ?>