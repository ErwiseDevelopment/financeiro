<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$hoje = date('Y-m-d');
$tres_dias_depois = date('Y-m-d', strtotime('+3 days'));

$fmt = new IntlDateFormatter('pt_BR', IntlDateFormatter::LONG, IntlDateFormatter::NONE);

// 1. ALERTAS
$stmt_alerta = $pdo->prepare("SELECT c.*, cat.categoriadescricao 
    FROM contas c 
    JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contasituacao = 'Pendente' AND c.contatipo = 'Saída' 
    AND c.cartoid IS NULL AND c.contavencimento <= ? 
    ORDER BY c.contavencimento ASC");
$stmt_alerta->execute([$uid, $tres_dias_depois]);
$alertas = $stmt_alerta->fetchAll();

// 2. SALDO ACUMULADO (Anterior + Atual)
$stmt_anterior = $pdo->prepare("SELECT 
    SUM(CASE WHEN contatipo = 'Entrada' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) -
    SUM(CASE WHEN contatipo = 'Saída' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) as saldo_acumulado
    FROM contas WHERE usuarioid = ? AND contacompetencia < ?");
$stmt_anterior->execute([$uid, $mes_filtro]);
$saldo_anterior = $stmt_anterior->fetch()['saldo_acumulado'] ?? 0;

// 3. RESUMO DO MÊS E CÁLCULOS NOVOS
$sql = $pdo->prepare("SELECT 
    SUM(CASE WHEN contatipo = 'Entrada' THEN contavalor ELSE 0 END) as entradas,
    SUM(CASE WHEN contatipo = 'Saída' THEN contavalor ELSE 0 END) as saidas,
    SUM(CASE WHEN contatipo = 'Entrada' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) as entradas_pagas,
    SUM(CASE WHEN contatipo = 'Saída' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) as saidas_pagas,
    SUM(CASE WHEN cartoid IS NOT NULL AND contatipo = 'Saída' THEN contavalor ELSE 0 END) as total_cartao_mes,
    SUM(CASE WHEN cartoid IS NULL AND contatipo = 'Saída' THEN contavalor ELSE 0 END) as total_fixo_dinheiro
    FROM contas WHERE usuarioid = ? AND contacompetencia = ?");
$sql->execute([$uid, $mes_filtro]);
$resumo = $sql->fetch();

$tot_entradas = $resumo['entradas'] ?? 0;
$tot_saidas = $resumo['saidas'] ?? 0;
$total_cartao_mes = $resumo['total_cartao_mes'] ?? 0;
$total_fixo_dinheiro = $resumo['total_fixo_dinheiro'] ?? 0;

$saldo_mes_atual = ($resumo['entradas_pagas'] ?? 0) - ($resumo['saidas_pagas'] ?? 0);
$saldo_real = $saldo_anterior + $saldo_mes_atual;

// --- NOVO CÁLCULO SOLICITADO ---
// Saldo Real Disponível + Renda Total - Saída Total
$resumo_mes_final = $saldo_real + $tot_entradas - $tot_saidas;

// 4. LIMITES E USO DE CARTÃO
$sql_limite = $pdo->prepare("SELECT SUM(cartolimite) as limite_total FROM cartoes WHERE usuarioid = ?");
$sql_limite->execute([$uid]);
$limite_geral = $sql_limite->fetch()['limite_total'] ?? 0;

$sql_uso_total = $pdo->prepare("SELECT SUM(contavalor) as total_utilizado_real FROM contas 
                               WHERE usuarioid = ? AND cartoid IS NOT NULL AND contasituacao = 'Pendente'");
$sql_uso_total->execute([$uid]);
$total_utilizado_real = $sql_uso_total->fetch()['total_utilizado_real'] ?? 0;

$perc_fatura_mes = ($limite_geral > 0) ? ($total_cartao_mes / $limite_geral) * 100 : 0;

// 5. LISTAGEM MISTA
$stmt_dinheiro = $pdo->prepare("SELECT c.*, cat.categoriadescricao FROM contas c 
    JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contacompetencia = ? AND c.cartoid IS NULL ORDER BY c.contavencimento ASC");
$stmt_dinheiro->execute([$uid, $mes_filtro]);
$contas_dinheiro = $stmt_dinheiro->fetchAll();

$stmt_cartoes = $pdo->prepare("SELECT car.cartonome, car.cartoid, SUM(c.contavalor) as total_fatura 
    FROM contas c JOIN cartoes car ON c.cartoid = car.cartoid
    WHERE c.usuarioid = ? AND c.contacompetencia = ? GROUP BY car.cartoid, car.cartonome");
$stmt_cartoes->execute([$uid, $mes_filtro]);
$agrupado_cartoes = $stmt_cartoes->fetchAll();
?>

<style>
    :root { --indigo: #6366f1; --soft-bg: #f8fafc; }
    body { background-color: var(--soft-bg); font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; }
    .card-balance { background: white; border-radius: 28px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); border: none; }
    .card-resumo { background: #eef2ff; border: 1px solid #e0e7ff; border-radius: 28px; padding: 25px; border: none; }
    .card-credit { background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: white; border-radius: 24px; padding: 25px; text-decoration: none; display: block; transition: 0.2s; }
    .card-credit:hover { transform: translateY(-3px); color: white; opacity: 0.95; }
    .scroll-horizontal { display: flex; overflow-x: auto; gap: 15px; padding-bottom: 10px; scrollbar-width: none; }
    .scroll-horizontal::-webkit-scrollbar { display: none; }
    .month-pill { padding: 8px 18px; border-radius: 12px; background: white; border: 1px solid #e2e8f0; color: #64748b; font-weight: 600; text-decoration: none; white-space: nowrap; font-size: 0.85rem; }
    .month-pill.active { background: var(--indigo); color: white; border-color: var(--indigo); }
    .filter-btn { border: 1px solid #e2e8f0; background: white; color: #64748b; padding: 6px 16px; border-radius: 10px; font-size: 0.75rem; font-weight: 700; transition: 0.2s; }
    .filter-btn.active { background: var(--indigo); color: white; border-color: var(--indigo); }
    .transaction-item { background: white; border-radius: 18px; padding: 14px 20px; margin-bottom: 10px; border: 1px solid rgba(0,0,0,0.03); display: flex; align-items: center; }
    .badge-card-agrupado { background: #fff7ed; color: #c2410c; font-size: 0.6rem; font-weight: 800; padding: 4px 8px; border-radius: 6px; }
    .progress { height: 10px; border-radius: 10px; background: #e2e8f0; overflow: hidden; }
    .badge-fixa { background: #f1f5f9; color: #475569; font-size: 0.6rem; padding: 2px 6px; border-radius: 4px; font-weight: bold; }
</style>

<div class="container py-4">
    <div class="card-balance mb-4 shadow-sm">
        <div class="d-flex justify-content-between align-items-end mb-2">
            <div>
                <small class="fw-bold text-muted text-uppercase" style="font-size: 0.65rem;">Comprometimento da Renda</small>
                <h6 class="mb-0 fw-bold"><?= number_format(($tot_saidas / max($tot_entradas, 1)) * 100, 1) ?>% do mês</h6>
            </div>
            <div class="text-end">
                <small style="font-size: 0.65rem;" class="text-muted"><span class="text-info">●</span> Dinheiro <span class="text-warning ms-2">●</span> Cartão</small>
            </div>
        </div>
        <div class="progress shadow-sm">
            <div class="progress-bar bg-info" style="width: <?= ($total_fixo_dinheiro / max($tot_entradas, 1)) * 100 ?>%"></div>
            <div class="progress-bar bg-warning" style="width: <?= ($total_cartao_mes / max($tot_entradas, 1)) * 100 ?>%"></div>
        </div>
    </div>

    <?php if(!empty($alertas)): ?>
        <div class="mb-4 px-2">
            <span class="text-danger fw-bold small mb-2 d-block text-uppercase" style="letter-spacing: 1px;"><i class="bi bi-exclamation-triangle-fill me-1"></i> Atenção Prioritária</span>
            <div class="scroll-horizontal" id="alertSlider">
                <?php foreach($alertas as $a): $vencida = ($a['contavencimento'] < $hoje); ?>
                    <div class="card border-0 shadow-sm <?= $vencida ? 'bg-dark' : 'bg-danger' ?> text-white p-3 rounded-4 flex-shrink-0" style="width: 280px;">
                        <div class="d-flex justify-content-between mb-2">
                            <small class="fw-bold opacity-75 small"><?= $vencida ? '⚠️ ATRASADO' : '⏳ VENCE LOGO' ?></small>
                            <small class="opacity-75" style="font-size: 0.7rem;"><?= $a['categoriadescricao'] ?></small>
                        </div>
                        <h6 class="fw-bold text-truncate mb-3"><?= $a['contadescricao'] ?></h6>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold fs-5">R$ <?= number_format($a['contavalor'], 2, ',', '.') ?></span>
                            <a href="acoes_conta.php?acao=pagar&id=<?= $a['contasid'] ?>" class="btn btn-sm btn-light rounded-pill px-3 fw-bold">PAGAR</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="d-flex overflow-x-auto gap-2 mb-4 px-2" style="scrollbar-width: none;" id="monthSlider">
        <?php for($i = -1; $i <= 4; $i++): 
            $m = date('Y-m', strtotime("+$i month", strtotime(date('Y-m-01'))));
            $label = ucfirst((new IntlDateFormatter('pt_BR', 0, 0, null, null, 'MMM yy'))->format(strtotime($m."-01")));
        ?>
            <a href="?mes=<?= $m ?>" class="month-pill <?= $mes_filtro == $m ? 'active' : '' ?>"><?= $label ?></a>
        <?php endfor; ?>
    </div>

    <div class="row g-3 mb-4 px-2">
        <div class="col-12 col-md-4">
            <div class="card-balance h-100 shadow-sm">
                <small class="text-muted fw-bold text-uppercase d-block mb-1" style="font-size: 0.65rem;">Saldo Real Disponível</small>
                <h2 class="fw-bold mb-3 mt-1">R$ <?= number_format($saldo_real, 2, ',', '.') ?></h2>
                <div class="d-flex justify-content-between border-top pt-3">
                    <div><small class="text-muted d-block small">Renda</small><span class="fw-bold text-success">+ R$ <?= number_format($tot_entradas, 2, ',', '.') ?></span></div>
                    <div class="text-end"><small class="text-muted d-block small">Saída</small><span class="fw-bold text-danger">- R$ <?= number_format($tot_saidas, 2, ',', '.') ?></span></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card-resumo h-100 shadow-sm">
                <small class="text-primary fw-bold text-uppercase d-block mb-1" style="font-size: 0.65rem;">Resumo Mês (Projeção)</small>
                <h2 class="fw-bold mb-3 mt-1 text-primary">R$ <?= number_format($resumo_mes_final, 2, ',', '.') ?></h2>
                <p class="text-muted mb-0 small" style="line-height: 1.2;">Estimativa de saldo ao final do mês considerando todas as receitas e despesas.</p>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <a href="faturas.php" class="card-credit shadow-sm h-100">
                <div class="d-flex justify-content-between text-uppercase mb-1" style="font-size: 0.6rem; letter-spacing: 0.5px;">
                    <span class="opacity-50">Fatura Atual</span>
                    <span class="opacity-50">Limite</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="fw-bold fs-5">R$ <?= number_format($total_cartao_mes, 2, ',', '.') ?></span>
                    <span class="fw-bold opacity-75">R$ <?= number_format($limite_geral, 2, ',', '.') ?></span>
                </div>
                <div class="progress bg-white bg-opacity-20" style="height: 6px;">
                    <div class="progress-bar bg-white shadow-sm" style="width: <?= min($perc_fatura_mes, 100) ?>%"></div>
                </div>
            </a>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3 px-2">
        <div class="d-flex gap-2">
            <button class="filter-btn active" onclick="filtrar('todos', this)">Tudo</button>
            <button class="filter-btn" onclick="filtrar('Entrada', this)">Entradas</button>
            <button class="filter-btn" onclick="filtrar('Saída', this)">Saídas</button>
        </div>
        <button onclick="copiarFixas()" class="btn btn-sm text-primary fw-bold text-decoration-none">COPIAR FIXAS <i class="bi bi-arrow-repeat"></i></button>
    </div>

    <div class="px-1 mb-5" id="lista-contas">
        <?php foreach($contas_dinheiro as $c): 
            $pago = ($c['contasituacao'] == 'Pago');
        ?>
            <div class="transaction-item shadow-sm" data-tipo="<?= $c['contatipo'] ?>">
                <div class="flex-grow-1 text-truncate">
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <span class="fw-bold small text-truncate <?= $pago ? 'text-decoration-line-through text-muted' : 'text-dark' ?>"><?= $c['contadescricao'] ?></span>
                        <?php if(isset($c['contafixa']) && $c['contafixa']): ?> <span class="badge-fixa">FIXA</span> <?php endif; ?>
                    </div>
                    <small class="text-muted" style="font-size: 0.7rem;">
                        <?= date('d/m', strtotime($c['contavencimento'])) ?> • <?= $c['categoriadescricao'] ?>
                    </small>
                </div>
                <div class="text-end ms-3">
                    <span class="fw-bold d-block small <?= $c['contatipo'] == 'Entrada' ? 'text-success' : 'text-dark' ?>">
                        <?= $c['contatipo'] == 'Entrada' ? '+' : '-' ?> R$ <?= number_format($c['contavalor'], 2, ',', '.') ?>
                    </span>
                    <?php if(!$pago): ?>
                        <a href="acoes_conta.php?acao=pagar&id=<?= $c['contasid'] ?>" class="text-primary fw-bold text-decoration-none" style="font-size: 0.65rem;">PAGAR</a>
                    <?php else: ?>
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 0.8rem;"></i>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php foreach($agrupado_cartoes as $cart): ?>
            <div class="transaction-item shadow-sm" data-tipo="Saída" style="border-left: 5px solid #f59e0b;">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge-card-agrupado">CARTÃO</span>
                        <span class="fw-bold small text-dark"><?= $cart['cartonome'] ?></span>
                    </div>
                    <small class="text-muted" style="font-size: 0.7rem;">Fatura consolidada</small>
                </div>
                <div class="text-end ms-3">
                    <span class="fw-bold d-block small text-dark">- R$ <?= number_format($cart['total_fatura'], 2, ',', '.') ?></span>
                    <a href="faturas.php?cartoid=<?= $cart['cartoid'] ?>&mes=<?= $mes_filtro ?>" class="text-warning fw-bold text-decoration-none" style="font-size: 0.65rem;">VER DETALHES</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
// Funções de Scroll
function enableDragScroll(id) {
    const slider = document.getElementById(id);
    if (!slider) return;
    let isDown = false; let startX; let scrollLeft;
    slider.addEventListener('mousedown', (e) => { isDown = true; startX = e.pageX - slider.offsetLeft; scrollLeft = slider.scrollLeft; });
    slider.addEventListener('mouseleave', () => isDown = false);
    slider.addEventListener('mouseup', () => isDown = false);
    slider.addEventListener('mousemove', (e) => { if (!isDown) return; e.preventDefault(); const x = e.pageX - slider.offsetLeft; const walk = (x - startX) * 2; slider.scrollLeft = scrollLeft - walk; });
}
enableDragScroll('alertSlider');
enableDragScroll('monthSlider');

// Filtro Dinâmico
function filtrar(tipo, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.transaction-item').forEach(item => {
        if (tipo === 'todos') {
            item.style.display = 'flex';
        } else {
            item.style.display = (item.getAttribute('data-tipo') === tipo) ? 'flex' : 'none';
        }
    });
}

// Copiar Contas Fixas
function copiarFixas() {
    if (confirm("Deseja copiar as contas fixas deste mês para o mês seguinte?")) {
        window.location.href = "copiar_mes.php?mes_origem=<?= $mes_filtro ?>&apenas_fixas=1";
    }
}
</script>

<?php require_once "../includes/footer.php"; ?>