<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$hoje = date('Y-m-d');

// --- 1. SALDO ACUMULADO (SÓ O QUE FOI PAGO/RECEBIDO NO PASSADO) ---
$stmt_passado = $pdo->prepare("SELECT 
    SUM(CASE WHEN contatipo = 'Entrada' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) -
    SUM(CASE WHEN contatipo = 'Saída' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) as saldo_passado
    FROM contas WHERE usuarioid = ? AND contacompetencia < ?");
$stmt_passado->execute([$uid, $mes_filtro]);
$saldo_passado_limpo = $stmt_passado->fetch()['saldo_passado'] ?? 0;

// --- 2. BUSCA CONTAS ATRASADAS (PENDENTES DE MESES ANTERIORES) ---
$stmt_atrasadas = $pdo->prepare("SELECT c.*, cat.categoriadescricao 
    FROM contas c JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contacompetencia < ? AND c.contasituacao = 'Pendente' 
    ORDER BY c.contavencimento ASC");
$stmt_atrasadas->execute([$uid, $mes_filtro]);
$contas_atrasadas = $stmt_atrasadas->fetchAll();

// --- 3. RESUMO DO MÊS ATUAL ---
$stmt_resumo = $pdo->prepare("SELECT 
    SUM(CASE WHEN contatipo = 'Entrada' THEN contavalor ELSE 0 END) as e_total,
    SUM(CASE WHEN contatipo = 'Saída' THEN contavalor ELSE 0 END) as s_total,
    SUM(CASE WHEN contatipo = 'Entrada' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) as e_paga,
    SUM(CASE WHEN contatipo = 'Saída' AND contasituacao = 'Pago' THEN contavalor ELSE 0 END) as s_paga,
    SUM(CASE WHEN cartoid IS NOT NULL AND contatipo = 'Saída' THEN contavalor ELSE 0 END) as total_cartao_mes
    FROM contas WHERE usuarioid = ? AND contacompetencia = ?");
$stmt_resumo->execute([$uid, $mes_filtro]);
$res = $stmt_resumo->fetch();

$e_total = $res['e_total'] ?? 0;
$s_total = $res['s_total'] ?? 0;
$e_paga = $res['e_paga'] ?? 0;
$s_paga = $res['s_paga'] ?? 0;

// Saldo Real = Saldo passado (pago) + o que recebeu hoje - o que pagou hoje
$saldo_real_agora = $saldo_passado_limpo + ($e_paga - $s_paga);

// Projeção Final = Saldo Real + o que falta entrar - o que falta sair (do mês atual)
$projecao_final_mes = $saldo_passado_limpo + ($e_total - $s_total);

// --- 4. CARTÕES E LIMITES ---
$sql_limite = $pdo->prepare("SELECT SUM(cartolimite) as limite_total FROM cartoes WHERE usuarioid = ?");
$sql_limite->execute([$uid]);
$limite_geral = $sql_limite->fetch()['limite_total'] ?? 0;

$sql_uso_total = $pdo->prepare("SELECT SUM(contavalor) as total_pendente FROM contas 
                               WHERE usuarioid = ? AND cartoid IS NOT NULL AND contasituacao = 'Pendente'");
$sql_uso_total->execute([$uid]);
$total_uso_cartao = $sql_uso_total->fetch()['total_pendente'] ?? 0;

// --- 5. LISTAGEM DO MÊS ATUAL ---
$stmt_contas = $pdo->prepare("SELECT c.*, cat.categoriadescricao FROM contas c 
    JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contacompetencia = ? AND c.cartoid IS NULL ORDER BY c.contavencimento ASC");
$stmt_contas->execute([$uid, $mes_filtro]);
$contas_lista = $stmt_contas->fetchAll();

$stmt_agrup_cartao = $pdo->prepare("SELECT car.cartonome, car.cartoid, SUM(c.contavalor) as total_fatura 
    FROM contas c JOIN cartoes car ON c.cartoid = car.cartoid
    WHERE c.usuarioid = ? AND c.contacompetencia = ? GROUP BY car.cartoid, car.cartonome");
$stmt_agrup_cartao->execute([$uid, $mes_filtro]);
$cartoes_resumo = $stmt_agrup_cartao->fetchAll();
?>

<style>
    :root { --primary: #4361ee; --bg: #f8fafc; --danger: #ef4444; }
    body { background-color: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; }
    .card-main { background: white; border-radius: 24px; padding: 22px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.02); }
    .card-credit { background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: white; border-radius: 24px; padding: 22px; text-decoration: none; display: block; }
    .month-pill { padding: 8px 16px; border-radius: 12px; background: white; border: 1px solid #e2e8f0; color: #64748b; text-decoration: none; font-weight: 600; font-size: 0.8rem; }
    .month-pill.active { background: var(--primary); color: white; border-color: var(--primary); }
    .transaction-item { background: white; border-radius: 16px; padding: 12px 18px; margin-bottom: 8px; border: 1px solid rgba(0,0,0,0.02); display: flex; align-items: center; }
    .atrasada-item { border-left: 4px solid var(--danger); background: #fff1f2; }
    .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 5px; }
</style>

<div class="container py-4">
    
    <div class="d-flex overflow-x-auto gap-2 mb-4 pb-1" style="scrollbar-width: none;">
        <?php for($i = -1; $i <= 4; $i++): 
            $m = date('Y-m', strtotime("+$i month", strtotime(date('Y-m-01'))));
            $label = ucfirst((new IntlDateFormatter('pt_BR', 0, 0, null, null, 'MMM yy'))->format(strtotime($m."-01")));
        ?>
            <a href="?mes=<?= $m ?>" class="month-pill <?= $mes_filtro == $m ? 'active' : '' ?>"><?= $label ?></a>
        <?php endfor; ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-7">
            <div class="card-main h-100">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <small class="text-muted fw-bold text-uppercase" style="font-size: 0.65rem;">Saldo Real (Disponível)</small>
                        <h2 class="fw-bold mb-0 <?= $saldo_real_agora >= 0 ? 'text-dark' : 'text-danger' ?>">
                            R$ <?= number_format($saldo_real_agora, 2, ',', '.') ?>
                        </h2>
                    </div>
                    <div class="text-end">
                        <small class="text-muted fw-bold text-uppercase" style="font-size: 0.65rem;">Projeção Final</small>
                        <h5 class="fw-bold mb-0 text-primary">R$ <?= number_format($projecao_final_mes, 2, ',', '.') ?></h5>
                    </div>
                </div>

                <div class="row g-2 border-top pt-3 mt-2">
                    <div class="col-6">
                        <small class="text-muted d-block small">Entradas do Mês</small>
                        <span class="fw-bold text-success">+ R$ <?= number_format($e_total, 2, ',', '.') ?></span>
                    </div>
                    <div class="col-6 text-end">
                        <small class="text-muted d-block small">Saídas do Mês</small>
                        <span class="fw-bold text-danger">- R$ <?= number_format($s_total, 2, ',', '.') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-5">
            <a href="faturas.php" class="card-credit h-100 shadow-sm">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <small class="opacity-75 fw-bold text-uppercase" style="font-size: 0.6rem;">Limite em Uso</small>
                    <i class="bi bi-credit-card-2-front fs-4 opacity-50"></i>
                </div>
                <h4 class="fw-bold mb-3">R$ <?= number_format($total_uso_cartao, 2, ',', '.') ?></h4>
                <div class="progress bg-white bg-opacity-20 mb-3" style="height: 4px;">
                    <div class="progress-bar bg-white" style="width: <?= ($limite_geral > 0) ? ($total_uso_cartao/$limite_geral)*100 : 0 ?>%"></div>
                </div>
                <div class="d-flex justify-content-between small">
                    <span class="opacity-75">Faturas <?= date('m/y', strtotime($mes_filtro)) ?>:</span>
                    <span class="fw-bold text-white">R$ <?= number_format($res['total_cartao_mes'] ?? 0, 2, ',', '.') ?></span>
                </div>
            </a>
        </div>
    </div>

    <div class="px-1">
        
        <?php if(!empty($contas_atrasadas)): ?>
            <h6 class="text-danger fw-bold mt-4 mb-3 small text-uppercase"><i class="bi bi-clock-history"></i> Pendências Atrasadas (Meses Anteriores)</h6>
            <?php foreach($contas_atrasadas as $ca): ?>
                <div class="transaction-item atrasada-item shadow-sm">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center">
                            <span class="fw-bold small text-danger"><?= $ca['contadescricao'] ?></span>
                        </div>
                        <small class="text-muted" style="font-size: 0.7rem;">Venceu em: <?= date('d/m/y', strtotime($ca['contavencimento'])) ?> • <?= $ca['categoriadescricao'] ?></small>
                    </div>
                    <div class="text-end">
                        <span class="fw-bold d-block small text-danger">R$ <?= number_format($ca['contavalor'], 2, ',', '.') ?></span>
                        <a href="acoes_conta.php?acao=pagar&id=<?= $ca['contasid'] ?>" class="btn-link text-decoration-none fw-bold text-danger" style="font-size: 0.65rem;">PAGAR AGORA</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h6 class="fw-bold text-muted mt-4 mb-3 small text-uppercase">Movimentações de <?= date('M/y', strtotime($mes_filtro)) ?></h6>
        <?php foreach($contas_lista as $c): $pago = ($c['contasituacao'] == 'Pago'); ?>
            <div class="transaction-item shadow-sm">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center">
                        <span class="status-dot <?= $pago ? 'bg-success' : 'bg-warning' ?>"></span>
                        <span class="fw-bold small <?= $pago ? 'text-muted text-decoration-line-through' : '' ?>"><?= $c['contadescricao'] ?></span>
                    </div>
                    <small class="text-muted" style="font-size: 0.7rem;"><?= date('d/m', strtotime($c['contavencimento'])) ?> • <?= $c['categoriadescricao'] ?></small>
                </div>
                <div class="text-end">
                    <span class="fw-bold d-block small <?= $c['contatipo'] == 'Entrada' ? 'text-success' : 'text-dark' ?>">
                        <?= $c['contatipo'] == 'Entrada' ? '+' : '-' ?> R$ <?= number_format($c['contavalor'], 2, ',', '.') ?>
                    </span>
                    <a href="acoes_conta.php?acao=pagar&id=<?= $c['contasid'] ?>" class="btn-link text-decoration-none fw-bold" style="font-size: 0.65rem; color: <?= $pago ? '#10b981' : '#4361ee' ?>;">
                        <?= $pago ? 'CONCLUÍDO' : 'DAR BAIXA' ?>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if(!empty($cartoes_resumo)): ?>
            <h6 class="fw-bold text-muted mt-4 mb-3 small text-uppercase">Resumo por Cartão</h6>
            <?php foreach($cartoes_resumo as $cart): ?>
                <div class="transaction-item shadow-sm" style="border-left: 4px solid #f59e0b;">
                    <div class="flex-grow-1">
                        <span class="fw-bold small d-block"><?= $cart['cartonome'] ?></span>
                        <small class="text-muted" style="font-size: 0.7rem;">Fatura deste mês</small>
                    </div>
                    <div class="text-end">
                        <span class="fw-bold d-block small">- R$ <?= number_format($cart['total_fatura'], 2, ',', '.') ?></span>
                        <a href="faturas.php?cartoid=<?= $cart['cartoid'] ?>&mes=<?= $mes_filtro ?>" class="text-warning fw-bold text-decoration-none" style="font-size: 0.65rem;">DETALHES</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>