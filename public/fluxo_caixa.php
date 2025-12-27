<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$mensagem = "";

// --- LÓGICA DE EXCLUSÃO ---
if (isset($_GET['acao']) && $_GET['acao'] === 'excluir' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = $pdo->prepare("DELETE FROM contas WHERE contasid = ? AND usuarioid = ?");
    if ($sql->execute([$id, $uid])) {
        $mensagem = "<div class='alert alert-danger border-0 shadow-sm small py-2 rounded-3 mb-4 text-center'>Lançamento excluído!</div>";
    }
}

// --- LÓGICA DE ATUALIZAÇÃO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'editar') {
    $id = $_POST['id'];
    $descricao = $_POST['descricao'];
    $valor = $_POST['valor'];
    $vencimento = $_POST['vencimento'];
    $cartoid = !empty($_POST['cartoid']) ? $_POST['cartoid'] : null;
    $contafixa = isset($_POST['contafixa']) ? 1 : 0;
    $competencia = date('Y-m', strtotime($vencimento));

    $sql = $pdo->prepare("UPDATE contas SET contadescricao = ?, contavalor = ?, contavencimento = ?, contacompetencia = ?, cartoid = ?, contafixa = ? WHERE contasid = ? AND usuarioid = ?");
    if ($sql->execute([$descricao, $valor, $vencimento, $competencia, $cartoid, $contafixa, $id, $uid])) {
        $mensagem = "<div class='alert alert-success border-0 shadow-sm small py-2 rounded-3 mb-4 text-center'>Atualizado com sucesso!</div>";
    }
}

// Consultas
$stmt_cartoes = $pdo->prepare("SELECT * FROM cartoes WHERE usuarioid = ? ORDER BY cartonome ASC");
$stmt_cartoes->execute([$uid]);
$lista_cartoes = $stmt_cartoes->fetchAll();

$stmt_entradas = $pdo->prepare("SELECT c.* FROM contas c WHERE c.usuarioid = ? AND c.contacompetencia = ? AND c.contatipo = 'Entrada' ORDER BY c.contavencimento ASC");
$stmt_entradas->execute([$uid, $mes_filtro]);
$entradas = $stmt_entradas->fetchAll();

$stmt_saidas = $pdo->prepare("SELECT c.* FROM contas c WHERE c.usuarioid = ? AND c.contacompetencia = ? AND c.contatipo = 'Saída' AND c.cartoid IS NULL ORDER BY c.contavencimento ASC");
$stmt_saidas->execute([$uid, $mes_filtro]);
$saidas = $stmt_saidas->fetchAll();

$stmt_faturas = $pdo->prepare("
    SELECT car.cartonome, car.cartoid, SUM(c.contavalor) as total_fatura 
    FROM contas c 
    JOIN cartoes car ON c.cartoid = car.cartoid 
    WHERE c.usuarioid = ? 
    AND (
        (DAY(c.contavencimento) < car.cartofechamento AND DATE_FORMAT(DATE_ADD(CONCAT(c.contacompetencia, '-01'), INTERVAL 1 MONTH), '%Y-%m') = ?)
        OR 
        (DAY(c.contavencimento) >= car.cartofechamento AND DATE_FORMAT(DATE_ADD(CONCAT(c.contacompetencia, '-01'), INTERVAL 2 MONTH), '%Y-%m') = ?)
    )
    GROUP BY car.cartoid");
$stmt_faturas->execute([$uid, $mes_filtro, $mes_filtro]);
$faturas_mes = $stmt_faturas->fetchAll();

$total_entradas = array_sum(array_column($entradas, 'contavalor'));
$total_saidas_diretas = array_sum(array_column($saidas, 'contavalor'));
$total_faturas = array_sum(array_column($faturas_mes, 'total_fatura'));
$total_geral_saidas = $total_saidas_diretas + $total_faturas;

$fmt_mes = new IntlDateFormatter('pt_BR', 0, 0, null, null, 'MMMM yyyy');
$titulo_mes = ucfirst($fmt_mes->format(strtotime($mes_filtro."-01")));
?>

<style>
    body { background-color: #f4f7f6; color: #334155; }
    .card-bilateral { border: none; border-radius: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.02); background: #fff; overflow: hidden; }
    .list-group-item { border: none; border-bottom: 1px solid #f1f5f9; padding: 1.2rem 1.2rem; transition: background 0.2s; }
    .list-group-item:last-child { border-bottom: none; }
    
    .btn-action { font-size: 0.65rem; font-weight: 700; border-radius: 12px; padding: 6px 14px; text-transform: uppercase; letter-spacing: 0.5px; }
    .badge-fixa { background: #f1f5f9; color: #64748b; font-size: 0.6rem; padding: 3px 7px; border-radius: 6px; font-weight: 700; }
    
    .fatura-highlight { background-color: #fdfaff; border-left: 5px solid #8b5cf6 !important; }
    .text-decoration-line-through { opacity: 0.4; filter: grayscale(1); }
    
    .month-pill { white-space: nowrap; padding: 10px 22px; border-radius: 16px; background: #fff; color: #64748b; text-decoration: none; font-weight: 600; font-size: 0.85rem; border: 1px solid #e2e8f0; transition: all 0.3s ease; }
    .month-pill.active { background: #0284c7; color: #fff; border-color: #0284c7; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3); }
    
    .section-title { font-size: 0.75rem; font-weight: 800; color: #94a3b8; letter-spacing: 1px; text-transform: uppercase; }
    .total-amount { font-size: 1.1rem; font-weight: 800; }
    
    /* Scrollbar oculta */
    #monthSlider::-webkit-scrollbar { display: none; }
</style>

<div class="container py-4">


    <div class="d-flex overflow-x-auto gap-2 mb-4 px-2" id="monthSlider">
        <?php for($i = -1; $i <= 5; $i++): 
            $m = date('Y-m', strtotime("+$i month", strtotime(date('Y-m-01'))));
            $label = (new IntlDateFormatter('pt_BR', 0, 0, null, null, 'MMM yy'))->format(strtotime($m."-01"));
        ?>
            <a href="?mes=<?= $m ?>" class="month-pill <?= $mes_filtro == $m ? 'active' : '' ?>"><?= ucfirst($label) ?></a>
        <?php endfor; ?>
    </div>

    <?= $mensagem ?>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="d-flex justify-content-between align-items-end mb-2 px-2">
                <span class="section-title">Receitas</span>
                <span class="total-amount text-success">R$ <?= number_format($total_entradas, 2, ',', '.') ?></span>
            </div>
            <div class="card-bilateral">
                <div class="list-group list-group-flush">
                    <?php if(empty($entradas)): ?>
                        <div class="p-4 text-center text-muted small">Nenhuma receita este mês.</div>
                    <?php endif; ?>
                    <?php foreach($entradas as $e): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="<?= $e['contasituacao'] == 'Pago' ? 'text-decoration-line-through' : '' ?>">
                                    <span class="fw-bold d-block mb-1 text-dark"><?= $e['contadescricao'] ?></span>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="small text-muted"><i class="bi bi-calendar3 me-1"></i><?= date('d/m', strtotime($e['contavencimento'])) ?></span>
                                        <?= $e['contafixa'] ? '<span class="badge-fixa">FIXA</span>' : '' ?>
                                    </div>
                                </div>
                                <span class="text-success fw-bold">R$ <?= number_format($e['contavalor'], 2, ',', '.') ?></span>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="acoes_conta.php?acao=<?= $e['contasituacao'] == 'Pendente' ? 'pagar' : 'estornar' ?>&id=<?= $e['contasid'] ?>" 
                                   class="btn btn-action <?= $e['contasituacao'] == 'Pendente' ? 'btn-success' : 'btn-light border text-muted' ?>">
                                    <?= $e['contasituacao'] == 'Pendente' ? '<i class="bi bi-check2-all me-1"></i> Receber' : 'Estornar' ?>
                                </a>
                                <button onclick='abrirModalEdicao(<?= json_encode($e) ?>)' class="btn btn-light border btn-action text-primary"><i class="bi bi-pencil me-1"></i></button>
                                <a href="?mes=<?= $mes_filtro ?>&acao=excluir&id=<?= $e['contasid'] ?>" onclick="return confirm('Excluir?')" class="btn btn-light border btn-action text-danger"><i class="bi bi-trash"></i></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="d-flex justify-content-between align-items-end mb-2 px-2">
                <span class="section-title">Despesas</span>
                <span class="total-amount text-danger">R$ <?= number_format($total_geral_saidas, 2, ',', '.') ?></span>
            </div>
            <div class="card-bilateral">
                <div class="list-group list-group-flush">
                    
                    <?php foreach($faturas_mes as $fat): ?>
                        <div class="list-group-item fatura-highlight">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3 text-primary">
                                        <i class="bi bi-credit-card-2-back fs-5"></i>
                                    </div>
                                    <div>
                                        <span class="fw-bold d-block text-dark">Fatura <?= $fat['cartonome'] ?></span>
                                        <small class="text-muted">Vence no dia 05</small>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="fw-bold text-danger d-block">R$ <?= number_format($fat['total_fatura'], 2, ',', '.') ?></span>
                                    <a href="faturas.php?cartoid=<?= $fat['cartoid'] ?>&mes=<?= $mes_filtro ?>" class="small fw-bold text-primary text-decoration-none">DETALHES <i class="bi bi-arrow-right"></i></a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php foreach($saidas as $s): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="<?= $s['contasituacao'] == 'Pago' ? 'text-decoration-line-through' : '' ?>">
                                    <span class="fw-bold d-block mb-1 text-dark"><?= $s['contadescricao'] ?></span>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="small text-muted"><i class="bi bi-calendar3 me-1"></i><?= date('d/m', strtotime($s['contavencimento'])) ?></span>
                                        <?= $s['contafixa'] ? '<span class="badge-fixa">FIXA</span>' : '' ?>
                                    </div>
                                </div>
                                <span class="text-danger fw-bold">R$ <?= number_format($s['contavalor'], 2, ',', '.') ?></span>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="acoes_conta.php?acao=<?= $s['contasituacao'] == 'Pendente' ? 'pagar' : 'estornar' ?>&id=<?= $s['contasid'] ?>" 
                                   class="btn btn-action <?= $s['contasituacao'] == 'Pendente' ? 'btn-danger' : 'btn-light border text-muted' ?>">
                                    <?= $s['contasituacao'] == 'Pendente' ? '<i class="bi bi-cash-stack me-1"></i> Pagar' : 'Estornar' ?>
                                </a>
                                <button onclick='abrirModalEdicao(<?= json_encode($s) ?>)' class="btn btn-light border btn-action text-primary"><i class="bi bi-pencil"></i></button>
                                <a href="?mes=<?= $mes_filtro ?>&acao=excluir&id=<?= $s['contasid'] ?>" onclick="return confirm('Excluir?')" class="btn btn-light border btn-action text-danger"><i class="bi bi-trash"></i></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarConta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 28px;">
            <div class="modal-header border-0 px-4 pt-4 pb-0">
                <h5 class="fw-bold">Editar Lançamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">DESCRIÇÃO</label>
                        <input type="text" name="descricao" id="edit_descricao" class="form-control form-control-lg bg-light border-0" style="font-size: 1rem;" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">VALOR (R$)</label>
                            <input type="number" step="0.01" name="valor" id="edit_valor" class="form-control form-control-lg bg-light border-0 fw-bold text-danger" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">DATA</label>
                            <input type="date" name="vencimento" id="edit_vencimento" class="form-control form-control-lg bg-light border-0" required>
                        </div>
                    </div>
                    <div class="mb-4" id="edit_div_cartao">
                        <label class="form-label small fw-bold text-muted">PAGAMENTO VIA</label>
                        <select name="cartoid" id="edit_cartoid" class="form-select form-select-lg bg-light border-0">
                            <option value="">Saldo em Conta (Débito)</option>
                            <?php foreach($lista_cartoes as $c): ?>
                                <option value="<?= $c['cartoid'] ?>"><?= $c['cartonome'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check form-switch p-3 bg-light rounded-4 d-flex justify-content-between align-items-center">
                        <label class="form-check-label small fw-bold mb-0" for="edit_contafixa">Lançamento Fixo Mensal</label>
                        <input class="form-check-input ms-0" type="checkbox" name="contafixa" id="edit_contafixa" value="1">
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" class="btn btn-primary w-100 py-3 rounded-4 fw-bold shadow">SALVAR ALTERAÇÕES</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalEdicao(conta) {
    document.getElementById('edit_id').value = conta.contasid;
    document.getElementById('edit_descricao').value = conta.contadescricao;
    document.getElementById('edit_valor').value = conta.contavalor;
    document.getElementById('edit_vencimento').value = conta.contavencimento;
    document.getElementById('edit_cartoid').value = conta.cartoid || "";
    document.getElementById('edit_contafixa').checked = (conta.contafixa == 1);
    
    // Se for entrada, esconde a opção de cartão
    document.getElementById('edit_div_cartao').style.display = (conta.contatipo === 'Entrada') ? 'none' : 'block';
    
    new bootstrap.Modal(document.getElementById('modalEditarConta')).show();
}
</script>

<?php require_once "../includes/footer.php"; ?>