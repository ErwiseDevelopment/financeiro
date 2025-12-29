<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$hoje = date('Y-m-d');
$tres_dias_depois = date('Y-m-d', strtotime('+3 days'));

$fmt = new IntlDateFormatter('pt_BR', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
$data_extenso = $fmt->format(new DateTime()); 

// 1. ALERTAS (Saídas Pendentes Atrasadas ou Próximas)
$stmt_alerta = $pdo->prepare("SELECT c.*, cat.categoriadescricao 
    FROM contas c 
    JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contasituacao = 'Pendente' AND c.contatipo = 'Saída'
    AND c.contavencimento <= ? ORDER BY c.contavencimento ASC");
$stmt_alerta->execute([$uid, $tres_dias_depois]);
$alertas = $stmt_alerta->fetchAll();

// --- NOVO: CÁLCULO DO SALDO ACUMULADO (MESES ANTERIORES) ---
// Soma tudo que entrou e saiu (PAGO) antes do mês atual selecionado
$stmt_anterior = $pdo->prepare("SELECT 
    SUM(CASE WHEN contatipo = 'Entrada' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) -
    SUM(CASE WHEN contatipo = 'Saída' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) as saldo_acumulado
    FROM contas 
    WHERE usuarioid = ? AND contacompetencia < ?");
$stmt_anterior->execute([$uid, $mes_filtro]);
$saldo_anterior = $stmt_anterior->fetch()['saldo_acumulado'] ?? 0;

// 2. RESUMO DO MÊS DETALHADO (Dinheiro vs Cartão)
$sql = $pdo->prepare("SELECT 
    SUM(CASE WHEN contatipo = 'Entrada' THEN contavalor ELSE 0 END) as entradas,
    SUM(CASE WHEN contatipo = 'Saída' THEN contavalor ELSE 0 END) as saidas,
    SUM(CASE WHEN contatipo = 'Entrada' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) as entradas_pagas,
    SUM(CASE WHEN contatipo = 'Saída' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) as saidas_pagas,
    SUM(CASE WHEN cartoid IS NOT NULL AND contatipo = 'Saída' THEN contavalor ELSE 0 END) as total_cartao,
    SUM(CASE WHEN cartoid IS NULL AND contatipo = 'Saída' THEN contavalor ELSE 0 END) as total_fixo_dinheiro
    FROM contas WHERE usuarioid = ? AND contacompetencia = ?");
$sql->execute([$uid, $mes_filtro]);
$resumo = $sql->fetch();

// 3. ESTRATÉGIA DE DÍVIDAS E LIMITE DE CARTÃO
$sql_limite = $pdo->prepare("SELECT SUM(cartolimite) as limite_total FROM cartoes WHERE usuarioid = ?");
$sql_limite->execute([$uid]);
$limite_geral = $sql_limite->fetch()['limite_total'] ?? 0;

$sql_dividas = $pdo->prepare("SELECT SUM(contavalor) as total_pendente FROM contas WHERE usuarioid = ? AND contasituacao = 'Pendente' AND contatipo = 'Saída'");
$sql_dividas->execute([$uid]);
$total_pendente_geral = $sql_dividas->fetch()['total_pendente'] ?? 0;

$tot_entradas = $resumo['entradas'] ?? 0;
$tot_saidas = $resumo['saidas'] ?? 0;
$total_cartao = $resumo['total_cartao'] ?? 0;
$total_fixo_dinheiro = $resumo['total_fixo_dinheiro'] ?? 0;

// CÁLCULO FINAL DO SALDO REAL (Anterior + Atual)
$saldo_mes_atual = ($resumo['entradas_pagas'] ?? 0) - ($resumo['saidas_pagas'] ?? 0);
$saldo_real = $saldo_anterior + $saldo_mes_atual;

$uso_limite_pct = ($limite_geral > 0) ? ($total_cartao / $limite_geral) * 100 : 0;

// 4. LISTAGEM GERAL
$stmt = $pdo->prepare("SELECT c.*, cat.categoriadescricao, car.cartonome 
    FROM contas c 
    JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    LEFT JOIN cartoes car ON c.cartoid = car.cartoid
    WHERE c.usuarioid = ? AND c.contacompetencia = ? 
    ORDER BY c.contavencimento ASC");
$stmt->execute([$uid, $mes_filtro]);
$contas = $stmt->fetchAll();
?>

<style>
    :root { --indigo: #6366f1; --soft-bg: #f8fafc; }
    body { background-color: var(--soft-bg); font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; }

    .scroll-horizontal { display: flex; overflow-x: auto; gap: 15px; padding-bottom: 10px; cursor: grab; user-select: none; scrollbar-width: none; }
    .scroll-horizontal::-webkit-scrollbar { display: none; }
    
    .card-balance { background: white; border-radius: 28px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); border: none; }
    .card-debt { background: #fff1f2; border: 1px solid #fecdd3; border-radius: 20px; padding: 15px; }
    
    .card-credit { 
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%); 
        color: white; border-radius: 24px; padding: 25px; position: relative; overflow: hidden;
        text-decoration: none; display: block; transition: 0.2s;
    }
    .card-credit:hover { transform: translateY(-3px); color: white; opacity: 0.9; }
    
    .progress { height: 10px; border-radius: 10px; background: #e2e8f0; overflow: hidden; }
    .month-pill { padding: 8px 18px; border-radius: 12px; background: white; border: 1px solid #e2e8f0; color: #64748b; font-weight: 600; text-decoration: none; white-space: nowrap; font-size: 0.85rem; }
    .month-pill.active { background: var(--indigo); color: white; border-color: var(--indigo); }
    
    .filter-btn { border: 1px solid #e2e8f0; background: white; color: #64748b; padding: 6px 16px; border-radius: 10px; font-size: 0.75rem; font-weight: 700; transition: 0.2s; }
    .filter-btn.active { background: var(--indigo); color: white; border-color: var(--indigo); }

    .transaction-item { background: white; border-radius: 18px; padding: 14px 20px; margin-bottom: 10px; border: 1px solid rgba(0,0,0,0.03); display: flex; align-items: center; }
    .badge-card { background: #eef2ff; color: #4f46e5; font-size: 0.6rem; font-weight: 800; padding: 3px 8px; border-radius: 6px; text-transform: uppercase; }
    .badge-fixa { background: #f1f5f9; color: #64748b; font-size: 0.6rem; padding: 2px 6px; border-radius: 4px; margin-left: 5px; }
</style>

<div class="container py-4">
  

    <div class="card-balance mb-4">
        <div class="d-flex justify-content-between align-items-end mb-2">
            <div>
                <small class="fw-bold text-muted text-uppercase" style="font-size: 0.65rem;">Comprometimento da Renda</small>
                <h6 class="mb-0 fw-bold"><?= number_format(($tot_saidas / max($tot_entradas, 1)) * 100, 1) ?>% do mês</h6>
            </div>
            <div class="text-end">
                <small style="font-size: 0.65rem;" class="text-muted">
                    <span class="text-info">●</span> Dinheiro <span class="text-warning ms-2">●</span> Cartão
                </small>
            </div>
        </div>
        <div class="progress shadow-sm">
            <div class="progress-bar bg-info" style="width: <?= ($total_fixo_dinheiro / max($tot_entradas, 1)) * 100 ?>%"></div>
            <div class="progress-bar bg-warning" style="width: <?= ($total_cartao / max($tot_entradas, 1)) * 100 ?>%"></div>
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

    <div class="d-flex overflow-x-auto gap-2 mb-4 px-2" id="monthSlider" style="scrollbar-width: none;">
        <?php for($i = -1; $i <= 4; $i++): 
            $m = date('Y-m', strtotime("+$i month", strtotime(date('Y-m-01'))));
            $label = ucfirst((new IntlDateFormatter('pt_BR', 0, 0, null, null, 'MMM yy'))->format(strtotime($m."-01")));
        ?>
            <a href="?mes=<?= $m ?>" class="month-pill <?= $mes_filtro == $m ? 'active' : '' ?>"><?= $label ?></a>
        <?php endfor; ?>
    </div>

    <div class="row g-3 mb-4 px-2">
        <div class="col-12 col-md-7">
            <div class="card-balance h-100">
                <small class="text-muted fw-bold text-uppercase" style="font-size: 0.65rem;">Saldo Real Disponível</small>
                <h2 class="fw-bold mb-3 mt-1">R$ <?= number_format($saldo_real, 2, ',', '.') ?></h2>
                <div class="d-flex justify-content-between border-top pt-3 mt-2">
                    <div>
                        <small class="text-muted d-block small">Renda Total</small>
                        <span class="fw-bold text-success">+ R$ <?= number_format($tot_entradas, 2, ',', '.') ?></span>
                    </div>
                    <div class="text-end">
                        <small class="text-muted d-block small">Saída Total</small>
                        <span class="fw-bold text-danger">- R$ <?= number_format($tot_saidas, 2, ',', '.') ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-5">
            <div class="card-debt mb-3 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="fw-bold text-danger" style="font-size: 0.65rem;">DÍVIDA PENDENTE GERAL</small>
                    <i class="bi bi-bank2 text-danger"></i>
                </div>
                <h4 class="fw-bold text-danger mb-0">R$ <?= number_format($total_pendente_geral, 2, ',', '.') ?></h4>
            </div>
            
            <a href="faturas.php" class="card-credit shadow-sm">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <small class="fw-bold opacity-50">USO DO LIMITE TOTAL</small>
                        <h4 class="fw-bold mb-0">R$ <?= number_format($total_cartao, 2, ',', '.') ?></h4>
                        <small class="opacity-75" style="font-size: 0.7rem;">Limite: R$ <?= number_format($limite_geral, 2, ',', '.') ?></small>
                    </div>
                    <i class="bi bi-credit-card-2-front fs-3 opacity-25"></i>
                </div>
                <div class="progress bg-white bg-opacity-20" style="height: 6px;">
                    <div class="progress-bar bg-white" style="width: <?= min($uso_limite_pct, 100) ?>%"></div>
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
        <?php foreach($contas as $c): 
            $pago = ($c['contasituacao'] == 'Pago');
            $tipo = $c['contatipo'];
        ?>
            <div class="transaction-item shadow-sm" data-tipo="<?= $tipo ?>">
                <div class="flex-grow-1 text-truncate">
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <span class="fw-bold small text-truncate <?= $pago ? 'text-decoration-line-through text-muted' : 'text-dark' ?>"><?= $c['contadescricao'] ?></span>
                        <?php if($c['contafixa']): ?>
                            <span class="badge-fixa">FIXA</span>
                        <?php endif; ?>
                        <?php if($c['cartonome']): ?>
                            <span class="badge-card"><?= $c['cartonome'] ?></span>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted" style="font-size: 0.7rem;">
                        <?= date('d/m', strtotime($c['contavencimento'])) ?> • <?= $c['categoriadescricao'] ?>
                    </small>
                </div>
                <div class="text-end ms-3">
                    <span class="fw-bold d-block small <?= $tipo == 'Entrada' ? 'text-success' : 'text-dark' ?>">
                        <?= $tipo == 'Entrada' ? '+' : '-' ?> R$ <?= number_format($c['contavalor'], 2, ',', '.') ?>
                    </span>
                    <?php if(!$pago): ?>
                        <a href="acoes_conta.php?acao=pagar&id=<?= $c['contasid'] ?>" class="text-primary fw-bold text-decoration-none" style="font-size: 0.65rem;">PAGAR</a>
                    <?php else: ?>
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 0.8rem;"></i>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
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

function filtrar(tipo, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.transaction-item').forEach(item => {
        item.style.display = (tipo === 'todos' || item.dataset.tipo === tipo) ? 'flex' : 'none';
    });
}

function copiarFixas() {
    if (confirm("Deseja copiar as contas fixas deste mês para o mês seguinte?")) {
        window.location.href = "copiar_mes.php?mes_origem=<?= $mes_filtro ?>&apenas_fixas=1";
    }
}
</script>

<?php require_once "../includes/footer.php"; ?>