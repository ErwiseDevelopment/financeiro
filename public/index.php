<?php
require_once "../config/database.php";
require_once "../includes/header.php";
$uid = $_SESSION['usuarioid'];

$mes_filtro = $_GET['mes'] ?? date('Y-m');
$hoje = date('Y-m-d');
$tres_dias_depois = date('Y-m-d', strtotime('+3 days'));

$fmt = new IntlDateFormatter('pt_BR', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
$data_extenso = $fmt->format(new DateTime()); 

// 1. ALERTAS PRIORITÁRIOS
$stmt_alerta = $pdo->prepare("SELECT c.*, cat.categoriadescricao 
    FROM contas c 
    JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contasituacao = 'Pendente' AND c.contatipo = 'Saída'
    AND c.contavencimento <= ? ORDER BY c.contavencimento ASC");
$stmt_alerta->execute([$uid, $tres_dias_depois]);
$alertas = $stmt_alerta->fetchAll();

// 2. RESUMO FINANCEIRO
$sql = $pdo->prepare("SELECT 
    SUM(CASE WHEN contatipo = 'Entrada' THEN contavalor ELSE 0 END) as entradas,
    SUM(CASE WHEN contatipo = 'Saída' THEN contavalor ELSE 0 END) as saidas
    FROM contas WHERE usuarioid = ? AND contacompetencia = ?");
$sql->execute([$uid, $mes_filtro]);
$resumo = $sql->fetch();

$entradas_v = $resumo['entradas'] ?? 0;
$saidas_v = $resumo['saidas'] ?? 0;
$saldo = $entradas_v - $saidas_v;

// 3. LISTAGEM
$stmt = $pdo->prepare("SELECT c.*, cat.categoriadescricao FROM contas c 
    JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contacompetencia = ? 
    ORDER BY c.contavencimento ASC");
$stmt->execute([$uid, $mes_filtro]);
$contas = $stmt->fetchAll();
?>

<style>
    body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
    
    /* Seletor de Meses Elegante */
    .month-nav { display: flex; overflow-x: auto; gap: 10px; padding: 5px 0; scrollbar-width: none; }
    .month-nav::-webkit-scrollbar { display: none; }
    .btn-m { 
        padding: 8px 18px; border-radius: 12px; background: #fff; border: 1px solid #eee;
        color: #6c757d; font-weight: 600; font-size: 0.8rem; text-decoration: none; transition: 0.2s;
    }
    .btn-m.active { background: #0d6efd; color: #fff; border-color: #0d6efd; box-shadow: 0 4px 10px rgba(13,110,253,0.2); }

    /* Card Principal */
    .hero-card { background: #fff; border-radius: 24px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
    
    /* Itens da Lista */
    .finance-item { 
        background: #fff; border-radius: 16px; padding: 14px; margin-bottom: 10px;
        border: 1px solid rgba(0,0,0,0.04); transition: transform 0.2s;
    }
    .finance-item:active { transform: scale(0.98); }
    
    .status-badge { font-size: 0.6rem; letter-spacing: 0.5px; padding: 4px 8px; border-radius: 6px; font-weight: 800; }
</style>

<div class="container py-4">
    <header class="d-flex justify-content-between align-items-center mb-4 px-1">
        <div>
            <h5 class="fw-bold mb-0">ED <span class="text-primary">Pro</span></h5>
            <small class="text-muted"><?= $data_extenso ?></small>
        </div>
        <a href="logout.php" class="text-secondary fs-4"><i class="bi bi-person-circle"></i></a>
    </header>

    <?php if(!empty($alertas)): ?>
        <div class="mb-4">
            <h6 class="fw-bold text-danger small mb-3 px-1"><i class="bi bi-lightning-fill"></i> URGENTE</h6>
            <div class="d-flex gap-2 overflow-x-auto pb-2" style="scrollbar-width: none;">
                <?php foreach($alertas as $a): $atrasada = ($a['contavencimento'] < $hoje); ?>
                    <div class="card border-0 shadow-sm <?= $atrasada ? 'bg-dark' : 'bg-danger' ?> text-white p-3 rounded-4 flex-shrink-0" style="min-width: 220px;">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="badge bg-white bg-opacity-25 small"><?= $a['categoriadescricao'] ?></span>
                            <small class="fw-bold"><?= $atrasada ? '⚠️ ATRASADO' : '⏳ HOJE' ?></small>
                        </div>
                        <h6 class="fw-bold text-truncate small mb-2"><?= $a['contadescricao'] ?></h6>
                        <div class="d-flex justify-content-between align-items-center mt-auto">
                            <span class="fw-bold">R$ <?= number_format($a['contavalor'], 2, ',', '.') ?></span>
                            <a href="acoes_conta.php?acao=pagar&id=<?= $a['contasid'] ?>" class="btn btn-sm btn-light rounded-pill px-3 fw-bold" style="font-size: 0.65rem;">PAGAR</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <nav class="month-nav mb-4">
        <?php for($i = -1; $i <= 4; $i++): 
            $m = date('Y-m', strtotime("+$i month", strtotime(date('Y-m-01'))));
            $active = ($mes_filtro == $m) ? 'active' : '';
            $nome_mes = new IntlDateFormatter('pt_BR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'MMM yy');
        ?>
            <a href="?mes=<?= $m ?>" class="btn-m <?= $active ?>"><?= ucfirst($nome_mes->format(strtotime($m."-01"))) ?></a>
        <?php endfor; ?>
    </nav>

    <div class="hero-card p-4 mb-4 text-center">
        <span class="text-muted small fw-bold text-uppercase">Saldo do Mês</span>
        <h2 class="display-6 fw-bold my-2 <?= $saldo >= 0 ? 'text-dark' : 'text-danger' ?>">
            R$ <?= number_format($saldo, 2, ',', '.') ?>
        </h2>
        <div class="row g-0 mt-4 pt-3 border-top">
            <div class="col-6 border-end">
                <small class="text-success fw-bold d-block small">RECEITAS</small>
                <span class="fw-bold">R$ <?= number_format($entradas_v, 2, ',', '.') ?></span>
            </div>
            <div class="col-6">
                <small class="text-danger fw-bold d-block small">DESPESAS</small>
                <span class="fw-bold">R$ <?= number_format($saidas_v, 2, ',', '.') ?></span>
            </div>
        </div>
    </div>

    <div class="px-1 mb-4">
        <button onclick="confirmarCopia()" class="btn btn-light w-100 rounded-4 border-0 shadow-sm text-secondary small fw-bold py-2">
            <i class="bi bi-arrow-right-short me-1"></i> Repetir mês em <?= date('M', strtotime("+1 month", strtotime($mes_filtro."-01"))) ?>
        </button>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3 px-1">
        <h6 class="fw-bold mb-0">Lançamentos</h6>
        <span class="text-muted" style="font-size: 0.7rem;"><?= count($contas) ?> itens</span>
    </div>

    <div class="mb-5">
        <?php if(empty($contas)): ?>
            <div class="p-5 text-center text-muted small bg-white rounded-4 border">Nenhum registro encontrado.</div>
        <?php else: foreach($contas as $c): ?>
            <div class="finance-item shadow-sm d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <div class="p-2 rounded-3 me-3 <?= $c['contatipo'] == 'Entrada' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                        <i class="bi <?= $c['contasituacao'] == 'Pago' ? 'bi-check2-all' : ($c['contatipo'] == 'Entrada' ? 'bi-plus' : 'bi-dash') ?> fs-5"></i>
                    </div>
                    <div>
                        <span class="fw-bold text-dark d-block small <?= $c['contasituacao'] == 'Pago' ? 'text-decoration-line-through opacity-50' : '' ?>">
                            <?= $c['contadescricao'] ?>
                        </span>
                        <div class="d-flex align-items-center gap-2">
                            <small class="text-muted" style="font-size: 0.65rem;">
                                <?= date('d/m', strtotime($c['contavencimento'])) ?> • <?= $c['categoriadescricao'] ?>
                            </small>
                            <?php if($c['contasituacao'] == 'Pago'): ?>
                                <span class="status-badge bg-success-subtle text-success">PAGO</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="text-end">
                    <span class="fw-bold small <?= $c['contatipo'] == 'Entrada' ? 'text-success' : 'text-dark' ?>">
                        <?= $c['contatipo'] == 'Entrada' ? '+' : '-' ?> R$ <?= number_format($c['contavalor'], 2, ',', '.') ?>
                    </span>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<script>
function confirmarCopia() {
    if (confirm("Deseja copiar todos os lançamentos para o mês seguinte?")) {
        window.location.href = "copiar_mes.php?mes_origem=<?= $mes_filtro ?>";
    }
}
</script>

<?php require_once "../includes/footer.php"; ?>