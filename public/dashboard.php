<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');

// --- 0. PREPARAÇÃO (Datas e Lógica de Competência) ---
$primeiro_dia_mes = $mes_filtro . "-01";
$campo_data_real = "COALESCE(competenciafatura, contacompetencia)"; 

// --- 1. SALDO REAL ACUMULADO (CAIXA ATUAL) ---
// Considera apenas o que foi PAGO no passado. É o dinheiro que você tem na mão hoje.
$stmt_passado_real = $pdo->prepare("SELECT 
    SUM(CASE WHEN contatipo = 'Entrada' THEN contavalor ELSE 0 END) -
    SUM(CASE WHEN contatipo = 'Saída' THEN contavalor ELSE 0 END) as saldo
    FROM contas 
    WHERE usuarioid = ? 
    AND $campo_data_real < ? 
    AND contasituacao = 'Pago'");
$stmt_passado_real->execute([$uid, $mes_filtro]);
$saldo_anterior_real = $stmt_passado_real->fetch()['saldo'] ?? 0;

// --- 2. SALDO GERAL DO PASSADO (PARA PROJEÇÃO) ---
// CORREÇÃO: Considera TUDO do passado (Pago + Pendente).
// Se você não pagou uma conta mês passado, ela entra aqui como dívida acumulada.
$stmt_passado_geral = $pdo->prepare("SELECT 
    SUM(CASE WHEN contatipo = 'Entrada' THEN contavalor ELSE 0 END) -
    SUM(CASE WHEN contatipo = 'Saída' THEN contavalor ELSE 0 END) as saldo
    FROM contas 
    WHERE usuarioid = ? 
    AND $campo_data_real < ?");
$stmt_passado_geral->execute([$uid, $mes_filtro]);
$saldo_anterior_geral = $stmt_passado_geral->fetch()['saldo'] ?? 0;

// --- 3. TOTAIS DO MÊS ATUAL ---
$stmt_totais = $pdo->prepare("SELECT 
    SUM(CASE WHEN contatipo = 'Entrada' THEN contavalor ELSE 0 END) as e_total,
    SUM(CASE WHEN contatipo = 'Saída' THEN contavalor ELSE 0 END) as s_total,
    SUM(CASE WHEN contatipo = 'Entrada' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) as e_paga,
    SUM(CASE WHEN contatipo = 'Saída' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) as s_paga,
    SUM(CASE WHEN cartoid IS NOT NULL AND contatipo = 'Saída' THEN contavalor ELSE 0 END) as total_cartao
    FROM contas 
    WHERE usuarioid = ? 
    AND $campo_data_real = ?");
$stmt_totais->execute([$uid, $mes_filtro]);
$res = $stmt_totais->fetch();

$e_total_mes = abs($res['e_total'] ?? 0);
$s_total_mes = abs($res['s_total'] ?? 0);
$e_paga_mes  = abs($res['e_paga'] ?? 0);
$s_paga_mes  = abs($res['s_paga'] ?? 0);
$v_cartao    = abs($res['total_cartao'] ?? 0);

// --- CÁLCULOS FINAIS ---

// Saldo Real Hoje: O que sobrou pago do passado + o que pagou/recebeu este mês
$saldo_real_hoje = $saldo_anterior_real + ($e_paga_mes - $s_paga_mes);

// Projeção Final: Saldo Geral Passado (inclui pendências antigas) + Resultado do Mês (Entradas - Saídas)
$projecao_final = $saldo_anterior_geral + ($e_total_mes - $s_total_mes);

$taxa_poupanca = ($e_total_mes > 0) ? (($e_total_mes - $s_total_mes) / $e_total_mes) * 100 : 0;
$percentual_gasto = ($e_total_mes > 0) ? ($s_total_mes / $e_total_mes) * 100 : 0;

// Status Cor
$status_cor = 'text-success';
if($percentual_gasto > 70) $status_cor = 'text-warning';
if($percentual_gasto > 90) $status_cor = 'text-danger';

// --- 4. SAÚDE DO CRÉDITO ---
$stmt_cartoes = $pdo->prepare("SELECT SUM(cartolimite) as limite_total FROM cartoes WHERE usuarioid = ?");
$stmt_cartoes->execute([$uid]);
$limite_total = $stmt_cartoes->fetch()['limite_total'] ?? 0;

$stmt_uso = $pdo->prepare("SELECT SUM(contavalor) as total FROM contas WHERE usuarioid = ? AND cartoid IS NOT NULL AND contasituacao = 'Pendente' AND contatipo = 'Saída'");
$stmt_uso->execute([$uid]);
$total_preso = abs($stmt_uso->fetch()['total'] ?? 0);
$perc_limite = ($limite_total > 0) ? ($total_preso / $limite_total) * 100 : 0;

// --- 5. DADOS PARA GRÁFICOS ---
// Histórico
$meses_hist = []; $valores_hist_e = []; $valores_hist_s = [];
for($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months", strtotime(date('Y-m-01'))));
    $stmt_h = $pdo->prepare("SELECT SUM(CASE WHEN contatipo='Entrada' THEN contavalor ELSE 0 END) as e, SUM(CASE WHEN contatipo='Saída' THEN contavalor ELSE 0 END) as s FROM contas WHERE usuarioid=? AND $campo_data_real=?");
    $stmt_h->execute([$uid, $m]);
    $h = $stmt_h->fetch();
    $meses_hist[] = ucfirst((new IntlDateFormatter('pt_BR',0,0,null,null,'MMM'))->format(strtotime($m."-01")));
    $valores_hist_e[] = abs($h['e']??0); $valores_hist_s[] = abs($h['s']??0);
}

// Pizza (Categorias)
$stmt_pizza = $pdo->prepare("SELECT cat.categoriadescricao as label, SUM(c.contavalor) as total FROM contas c JOIN categorias cat ON c.categoriaid = cat.categoriaid WHERE c.usuarioid=? AND $campo_data_real=? AND c.contatipo='Saída' GROUP BY cat.categoriaid ORDER BY total DESC");
$stmt_pizza->execute([$uid, $mes_filtro]);
$dados_pizza = $stmt_pizza->fetchAll(PDO::FETCH_ASSOC);

// Semanal
$stmt_sem = $pdo->prepare("SELECT FLOOR((DAY(contavencimento)-1)/7)+1 as semana, SUM(contavalor) as total FROM contas WHERE usuarioid=? AND $campo_data_real=? AND contatipo='Saída' GROUP BY semana ORDER BY semana");
$stmt_sem->execute([$uid, $mes_filtro]);
$semanal_res = $stmt_sem->fetchAll(PDO::FETCH_KEY_PAIR);
$valores_semanais = []; for($w=1; $w<=5; $w++) { $valores_semanais[] = $semanal_res[$w] ?? 0; }

// --- 6. LISTAGENS ---
// Lista Categorias (para o widget)
$stmt_cat = $pdo->prepare("SELECT cat.categoriaid, cat.categoriadescricao as label, SUM(c.contavalor) as total FROM contas c JOIN categorias cat ON c.categoriaid = cat.categoriaid WHERE c.usuarioid=? AND $campo_data_real=? AND c.contatipo='Saída' GROUP BY cat.categoriaid ORDER BY total DESC");
$stmt_cat->execute([$uid, $mes_filtro]);
$categorias_lista = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

// Lista Lançamentos
$stmt_all = $pdo->prepare("SELECT c.*, cat.categoriadescricao, car.cartonome FROM contas c LEFT JOIN categorias cat ON c.categoriaid = cat.categoriaid LEFT JOIN cartoes car ON c.cartoid = car.cartoid WHERE c.usuarioid=? AND $campo_data_real=? ORDER BY c.contavencimento DESC");
$stmt_all->execute([$uid, $mes_filtro]);
$todos_lancamentos = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

$titulo_mes = ucfirst((new IntlDateFormatter('pt_BR', 0, 0, null, null, 'MMMM yyyy'))->format(strtotime($primeiro_dia_mes)));
?>

<style>
    :root { --primary: #4361ee; --success: #10b981; --danger: #ef4444; --warning: #f59e0b; --dark: #1e293b; --purple: #7209b7; }
    body { background-color: #f8fafc; font-family: 'Plus Jakarta Sans', sans-serif; }
    .month-pill { padding: 10px 20px; border-radius: 14px; background: white; border: 1px solid #e2e8f0; color: #64748b; text-decoration: none; font-weight: 600; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
    .month-pill.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2); }
    .card-stat { border: none; border-radius: 20px; background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.02); height: 100%; transition: 0.3s; }
    .card-stat:hover { transform: translateY(-3px); }
    .icon-box { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; font-size: 1.1rem; }
    .bg-purple-gradient { background: linear-gradient(135deg, #7209b7 0%, #4361ee 100%); color: white; }
    .category-btn { cursor: pointer; padding: 12px; border-radius: 15px; border: 1px solid #f1f5f9; background: #fff; width: 100%; text-align: left; margin-bottom: 8px; transition: 0.2s; }
    .category-btn.active { border-color: var(--primary); background: #f0f3ff; }
    .transaction-item { display: flex; align-items: center; padding: 12px; border-radius: 12px; margin-bottom: 8px; background: #fff; border: 1px solid #f1f5f9; }
    .hidden { display: none !important; }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">Dashboard Analítico</h4>
        <span class="badge bg-white text-dark border px-3 py-2 rounded-pill shadow-sm"><?= $titulo_mes ?></span>
    </div>

    <div class="d-flex overflow-x-auto gap-2 mb-4 pb-2" style="scrollbar-width: none;">
        <?php for($i = -1; $i <= 4; $i++): 
            $m = date('Y-m', strtotime("+$i month", strtotime(date('Y-m-01'))));
            $label = ucfirst((new IntlDateFormatter('pt_BR', 0, 0, null, null, 'MMM yy'))->format(strtotime($m."-01")));
        ?>
            <a href="?mes=<?= $m ?>" class="month-pill <?= $mes_filtro == $m ? 'active' : '' ?>"><?= $label ?></a>
        <?php endfor; ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md">
            <div class="card-stat p-3 text-center text-md-start">
                <div class="icon-box bg-success bg-opacity-10 text-success mx-auto mx-md-0"><i class="bi bi-arrow-up"></i></div>
                <small class="text-muted fw-bold d-block" style="font-size: 0.6rem;">ENTRADAS</small>
                <h6 class="fw-bold mb-0">R$ <?= number_format($e_total_mes, 2, ',', '.') ?></h6>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card-stat p-3 text-center text-md-start">
                <div class="icon-box bg-danger bg-opacity-10 text-danger mx-auto mx-md-0"><i class="bi bi-arrow-down"></i></div>
                <small class="text-muted fw-bold d-block" style="font-size: 0.6rem;">SAÍDAS</small>
                <h6 class="fw-bold mb-0">R$ <?= number_format($s_total_mes, 2, ',', '.') ?></h6>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card-stat p-3 text-center text-md-start">
                <div class="icon-box bg-info bg-opacity-10 text-info mx-auto mx-md-0"><i class="bi bi-wallet2"></i></div>
                <small class="text-muted fw-bold d-block" style="font-size: 0.6rem;">SALDO REAL HOJE</small>
                <h6 class="fw-bold mb-0">R$ <?= number_format($saldo_real_hoje, 2, ',', '.') ?></h6>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card-stat p-3 text-center text-md-start">
                <div class="icon-box bg-warning bg-opacity-10 text-warning mx-auto mx-md-0"><i class="bi bi-piggy-bank"></i></div>
                <small class="text-muted fw-bold d-block" style="font-size: 0.6rem;">ECONOMIA</small>
                <h6 class="fw-bold mb-0"><?= number_format($taxa_poupanca, 1) ?>%</h6>
            </div>
        </div>
        <div class="col-12 col-md">
            <div class="card-stat p-3 bg-purple-gradient text-white">
                <div class="icon-box bg-white bg-opacity-25 text-white"><i class="bi bi-calculator-fill"></i></div>
                <small class="text-white text-opacity-75 fw-bold d-block" style="font-size: 0.6rem;">PROJEÇÃO FINAL</small>
                <h6 class="fw-bold mb-0">R$ <?= number_format($projecao_final, 2, ',', '.') ?></h6>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-7">
            <div class="card-stat p-4 bg-dark text-white">
                <h6 class="fw-bold mb-4 small text-uppercase opacity-75">Saúde do Crédito (Global)</h6>
                <div class="row align-items-center">
                    <div class="col-md-6 border-end border-secondary border-opacity-50">
                        <small class="opacity-50 d-block mb-1">Limite Comprometido</small>
                        <h2 class="fw-bold mb-2">R$ <?= number_format($total_preso, 2, ',', '.') ?></h2>
                        <div class="progress mb-2" style="height: 6px; background: rgba(255,255,255,0.1);">
                            <div class="progress-bar bg-info" style="width: <?= min(100, $perc_limite) ?>%"></div>
                        </div>
                        <small class="text-info"><?= number_format($perc_limite, 1) ?>% em uso</small>
                    </div>
                    <div class="col-md-6 ps-md-4">
                        <small class="opacity-50 d-block mb-1">Disponível Total</small>
                        <h3 class="fw-bold text-success mb-2">R$ <?= number_format($limite_total - $total_preso, 2, ',', '.') ?></h3>
                        <small class="opacity-50 text-uppercase" style="font-size: 0.6rem;">Teto total: R$ <?= number_format($limite_total, 2, ',', '.') ?></small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-5">
            <div class="card-stat p-4 text-center d-flex flex-column justify-content-center border-start border-purple border-4">
                <h6 class="fw-bold mb-3 small text-uppercase text-muted">Comprometimento</h6>
                <div class="position-relative d-inline-flex align-items-center justify-content-center mb-3">
                    <h2 class="fw-bold <?= $status_cor ?> mb-0"><?= round($percentual_gasto) ?>%</h2>
                </div>
                <div class="progress" style="height: 10px; border-radius: 20px; background: #eee;">
                    <div class="progress-bar bg-<?= ($percentual_gasto > 80) ? 'danger' : (($percentual_gasto > 50) ? 'warning' : 'success') ?>" style="width: <?= min(100, $percentual_gasto) ?>%"></div>
                </div>
                <?php if($percentual_gasto < 70): ?>
                    <small class="mt-3 text-success fw-bold"><i class="bi bi-check-circle-fill"></i> Saúde excelente</small>
                <?php else: ?>
                    <small class="mt-3 text-warning fw-bold"><i class="bi bi-exclamation-triangle-fill"></i> Atenção aos gastos</small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-6">
            <div class="card-stat p-4">
                <h6 class="fw-bold mb-4 small text-uppercase text-muted">Distribuição por Categoria</h6>
                <div style="height: 250px;"><canvas id="chartPizza"></canvas></div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card-stat p-4">
                <h6 class="fw-bold mb-4 small text-uppercase text-muted">Gastos Semanais</h6>
                <div style="height: 250px;"><canvas id="chartSemanal"></canvas></div>
            </div>
        </div>
    </div>

    <div class="card-stat p-4 mb-4">
        <h6 class="fw-bold mb-4 small text-uppercase text-muted">Fluxo de Caixa (6 Meses)</h6>
        <div style="height: 250px;"><canvas id="chartEvolucao"></canvas></div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-4">
            <div class="card-stat p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h6 class="fw-bold m-0 small text-uppercase text-muted">Filtrar por Categoria</h6>
                    <a href="analise_categorias.php" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-bold" style="font-size: 0.65rem;">
                        <i class="bi bi-graph-up-arrow me-1"></i> VER ANÁLISE
                    </a>
                </div>
                <?php foreach($categorias_lista as $c): $p = ($s_total_mes > 0) ? ($c['total'] / $s_total_mes) * 100 : 0; ?>
                <button class="category-btn" onclick="filterCategory(<?= $c['categoriaid'] ?>, this)">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-bold"><?= $c['label'] ?></span>
                        <span class="small fw-bold">R$ <?= number_format($c['total'], 2, ',', '.') ?></span>
                    </div>
                    <div class="progress" style="height: 4px; background: #eee;"><div class="progress-bar bg-primary" style="width: <?= $p ?>%"></div></div>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-12 col-lg-8">
            <div class="card-stat p-4">
                <h6 class="fw-bold mb-4 small text-uppercase text-muted">Lançamentos da Competência</h6>
                <div id="extrato-container">
                    <?php foreach($todos_lancamentos as $l): $pago = ($l['contasituacao'] == 'Pago'); ?>
                    <div class="transaction-item js-item" data-catid="<?= $l['categoriaid'] ?>">
                        <div class="flex-grow-1 text-truncate">
                            <h6 class="mb-0 fw-bold <?= $pago ? 'text-muted text-decoration-line-through' : '' ?>" style="font-size: 0.8rem;"><?= $l['contadescricao'] ?></h6>
                            <small class="text-muted" style="font-size: 0.7rem;">
                                <?= date('d/m', strtotime($l['contavencimento'])) ?> • <?= $l['categoriadescricao'] ?> 
                                <?= $l['cartonome'] ? " • <span class='badge bg-warning text-dark' style='font-size:0.6rem'>{$l['cartonome']}</span>" : "" ?>
                            </small>
                        </div>
                        <div class="text-end ms-3">
                            <span class="fw-bold d-block <?= $l['contatipo'] == 'Saída' ? 'text-dark' : 'text-success' ?>" style="font-size: 0.8rem;">
                                <?= $l['contatipo'] == 'Saída' ? '-' : '+' ?> R$ <?= number_format(abs($l['contavalor']), 2, ',', '.') ?>
                            </span>
                            <span class="badge <?= $pago ? 'bg-success' : 'bg-light text-dark' ?>" style="font-size: 0.5rem;"><?= strtoupper($l['contasituacao']) ?></span>
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
        const isActive = btn.classList.contains('active');
        document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
        if (isActive) {
            document.querySelectorAll('.js-item').forEach(i => i.classList.remove('hidden'));
        } else {
            btn.classList.add('active');
            document.querySelectorAll('.js-item').forEach(i => i.classList.toggle('hidden', i.dataset.catid != catId));
        }
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
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('chartPizza'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($dados_pizza, 'label')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($dados_pizza, 'total')) ?>,
                backgroundColor: ['#4361ee', '#7209b7', '#f72585', '#4cc9f0', '#3a86ff'],
                borderWidth: 0
            }]
        },
        options: { maintainAspectRatio: false, cutout: '70%', plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById('chartSemanal'), {
        type: 'bar',
        data: {
            labels: ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4', 'Sem 5'],
            datasets: [{ label: 'Gastos', data: <?= json_encode($valores_semanais) ?>, backgroundColor: '#4361ee', borderRadius: 6 }]
        },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });
</script>

<?php require_once "../includes/footer.php"; ?>