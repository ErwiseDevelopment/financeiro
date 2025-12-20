<?php
require_once "../config/database.php";
require_once "../includes/header.php";
$uid = $_SESSION['usuarioid'];

// 1. GARANTE O MÊS ATUAL
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$hoje = date('Y-m-d');
$tres_dias_depois = date('Y-m-d', strtotime('+3 days'));

// Formatação de data
$fmt = new IntlDateFormatter('pt_BR', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
$data_extenso = $fmt->format(new DateTime()); 

// 2. BUSCA APENAS SAÍDAS CRÍTICAS (Atrasadas ou vencendo em 3 dias)
// Adicionado: AND c.contatipo = 'Saída'
$stmt_alerta = $pdo->prepare("SELECT c.*, cat.categoriadescricao 
    FROM contas c 
    JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? 
    AND c.contasituacao = 'Pendente' 
    AND c.contatipo = 'Saída'
    AND c.contavencimento <= ?
    ORDER BY c.contavencimento ASC");
$stmt_alerta->execute([$uid, $tres_dias_depois]);
$alertas = $stmt_alerta->fetchAll();

// 3. TOTAIS DO MÊS SELECIONADO
$sql = $pdo->prepare("SELECT 
    SUM(CASE WHEN contatipo = 'Entrada' THEN contavalor ELSE 0 END) as entradas,
    SUM(CASE WHEN contatipo = 'Saída' THEN contavalor ELSE 0 END) as saidas
    FROM contas WHERE usuarioid = ? AND contacompetencia = ?");
$sql->execute([$uid, $mes_filtro]);
$resumo = $sql->fetch();

$entradas = $resumo['entradas'] ?? 0;
$saidas = $resumo['saidas'] ?? 0;
$saldo = $entradas - $saidas;

// 4. LISTA GERAL DO MÊS
$stmt = $pdo->prepare("SELECT c.*, cat.categoriadescricao FROM contas c 
    JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contacompetencia = ? 
    ORDER BY c.contavencimento ASC");
$stmt->execute([$uid, $mes_filtro]);
$contas = $stmt->fetchAll();
?>

<div class="container py-4">
    <header class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="fw-bold mb-0">Finance<span class="text-primary">Pro</span></h5>
            <p class="text-muted small mb-0"><?= $data_extenso ?></p>
        </div>
        <a href="logout.php" class="btn btn-light rounded-4 border p-2 text-dark"><i class="bi bi-person-circle"></i></a>
    </header>

    <?php if(!empty($alertas)): ?>
        <div class="mb-4">
            <span class="form-label-caps text-danger mb-2 d-block"><i class="bi bi-exclamation-octagon-fill me-1"></i> Atenção Prioritária</span>
            <div class="d-flex gap-2 overflow-x-auto pb-2" style="scrollbar-width: none;">
                <?php foreach($alertas as $a): 
                    $atrasada = ($a['contavencimento'] < $hoje);
                    $cor_alerta = $atrasada ? 'bg-dark' : 'bg-danger'; // Diferencia atrasada de próxima
                ?>
                    <div class="card border-0 shadow-sm <?= $cor_alerta ?> text-white p-3 rounded-4 flex-shrink-0" style="min-width: 240px;">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="fw-bold opacity-75 text-uppercase" style="font-size: 0.6rem;">
                                <?= $atrasada ? '⚠️ ATRASADO' : '⏳ VENCE LOGO' ?>
                            </small>
                            <span style="font-size: 0.7rem;"><?= $a['categoriadescricao'] ?></span>
                        </div>
                        <h6 class="fw-bold text-truncate mb-2"><?= $a['contadescricao'] ?></h6>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold">R$ <?= number_format($a['contavalor'], 2, ',', '.') ?></span>
                            <a href="acoes_conta.php?acao=pagar&id=<?= $a['contasid'] ?>" class="btn btn-sm btn-light rounded-pill px-3 fw-bold shadow-sm" style="font-size: 0.7rem;">PAGAR</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <nav class="month-selector mb-4">
        <?php for($i = -1; $i <= 4; $i++): 
            $m = date('Y-m', strtotime("+$i month", strtotime(date('Y-m-01'))));
            $active = ($mes_filtro == $m) ? 'active' : '';
            $nome_mes_fmt = new IntlDateFormatter('pt_BR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'MMM yy');
            $label_mes = $nome_mes_fmt->format(strtotime($m."-01"));
        ?>
            <a href="?mes=<?= $m ?>" class="btn-month <?= $active ?>"><?= ucfirst($label_mes) ?></a>
        <?php endfor; ?>
    </nav>

    <section class="balance-hero shadow-sm mb-4 bg-white rounded-5 p-4 text-center border-0">
        <p class="text-muted small fw-bold text-uppercase mb-1">Saldo em <?= date('M', strtotime($mes_filtro)) ?></p>
        <h1 class="display-5 fw-bold <?= $saldo >= 0 ? 'text-dark' : 'text-danger' ?>">
            <span class="fs-4 opacity-50">R$</span> <?= number_format($saldo, 2, ',', '.') ?>
        </h1>
    </section>

    <div class="px-1 mb-4">
    <button onclick="confirmarCopia()" class="btn btn-light w-100 rounded-4 border py-2 shadow-sm text-secondary small fw-bold">
        <i class="bi bi-copy me-2"></i> COPIAR LANÇAMENTOS PARA <?= strtoupper(date('M/y', strtotime("+1 month", strtotime($mes_filtro . "-01")))) ?>
    </button>
</div>

<script>
function confirmarCopia() {
    if (confirm("Deseja duplicar todos os lançamentos deste mês para o próximo? Eles serão criados como 'Pendente'.")) {
        window.location.href = "copiar_mes.php?mes_origem=<?= $mes_filtro ?>";
    }
}
</script>

    <div class="row g-3 mb-4">
        <div class="col-6">
            <div class="card border-0 shadow-sm p-3 bg-white rounded-4">
                <i class="bi bi-arrow-down-left text-success fs-4 mb-1"></i>
                <small class="text-muted d-block fw-bold small">RECEITAS</small>
                <span class="fw-bold text-success">R$ <?= number_format($entradas, 2, ',', '.') ?></span>
            </div>
        </div>
        <div class="col-6">
            <div class="card border-0 shadow-sm p-3 bg-white rounded-4">
                <i class="bi bi-arrow-up-right text-danger fs-4 mb-1"></i>
                <small class="text-muted d-block fw-bold small">DESPESAS</small>
                <span class="fw-bold text-danger">R$ <?= number_format($saidas, 2, ',', '.') ?></span>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3 px-1">
        <h6 class="fw-bold mb-0">Atividades</h6>
        <span class="badge bg-light text-dark border rounded-pill"><?= count($contas) ?> lançamentos</span>
    </div>

    <div class="bg-white shadow-sm rounded-4 overflow-hidden mb-5 border">
        <?php if(empty($contas)): ?>
            <div class="p-5 text-center text-muted small">Nenhum registro encontrado.</div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach($contas as $c): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                        <div class="d-flex align-items-center">
                            <div class="p-2 rounded-3 me-3 <?= $c['contatipo'] == 'Entrada' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                <i class="bi <?= $c['contasituacao'] == 'Pago' ? 'bi-check-circle-fill' : ($c['contatipo'] == 'Entrada' ? 'bi-plus-lg' : 'bi-dash-lg') ?>"></i>
                            </div>
                            <div>
                                <span class="fw-bold text-dark d-block small <?= $c['contasituacao'] == 'Pago' ? 'text-decoration-line-through opacity-50' : '' ?>">
                                    <?= $c['contadescricao'] ?>
                                </span>
                                <small class="text-muted" style="font-size: 0.65rem;">
                                    <?= $c['categoriadescricao'] ?> • <?= date('d/m', strtotime($c['contavencimento'])) ?>
                                    <?php if($c['contasituacao'] == 'Pago'): ?> <span class="text-success fw-bold">PAGO</span> <?php endif; ?>
                                </small>
                            </div>
                        </div>
                        <span class="fw-bold small text-dark">R$ <?= number_format($c['contavalor'], 2, ',', '.') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>