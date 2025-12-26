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
        $mensagem = "<div class='alert alert-danger border-0 shadow-sm small py-2 rounded-3 mb-4'>Lançamento excluído com sucesso!</div>";
    }
}

// --- LÓGICA DE ATUALIZAÇÃO (EDITAR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'editar') {
    $id = $_POST['id'];
    $descricao = $_POST['descricao'];
    $valor = $_POST['valor'];
    $vencimento = $_POST['vencimento'];
    $competencia = date('Y-m', strtotime($vencimento));

    $sql = $pdo->prepare("UPDATE contas SET contadescricao = ?, contavalor = ?, contavencimento = ?, contacompetencia = ? WHERE contasid = ? AND usuarioid = ?");
    if ($sql->execute([$descricao, $valor, $vencimento, $competencia, $id, $uid])) {
        $mensagem = "<div class='alert alert-success border-0 shadow-sm small py-2 rounded-3 mb-4'>Lançamento atualizado com sucesso!</div>";
    }
}

// 1. Busca Entradas
$stmt_entradas = $pdo->prepare("SELECT c.*, cat.categoriadescricao 
    FROM contas c 
    LEFT JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contacompetencia = ? AND c.contatipo = 'Entrada'
    ORDER BY c.contavencimento ASC");
$stmt_entradas->execute([$uid, $mes_filtro]);
$entradas = $stmt_entradas->fetchAll();

// 2. Busca Saídas
$stmt_saidas = $pdo->prepare("SELECT c.*, cat.categoriadescricao 
    FROM contas c 
    LEFT JOIN categorias cat ON c.categoriaid = cat.categoriaid 
    WHERE c.usuarioid = ? AND c.contacompetencia = ? AND c.contatipo = 'Saída'
    ORDER BY c.contavencimento ASC");
$stmt_saidas->execute([$uid, $mes_filtro]);
$saidas = $stmt_saidas->fetchAll();

// Formatação do Título
$fmt_mes = new IntlDateFormatter('pt_BR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'MMMM yyyy');
$titulo_mes = $fmt_mes->format(strtotime($mes_filtro."-01"));
?>

<style>
    .card-bilateral { border: none; border-radius: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); overflow: hidden; background: #fff; }
    .list-group-item { border-left: none; border-right: none; border-top: none; padding: 1.25rem 1rem; transition: 0.2s; }
    .list-group-item:hover { background-color: #fcfcfc; }
    .btn-action { font-size: 0.7rem; font-weight: 800; letter-spacing: 0.5px; border-radius: 10px; padding: 5px 12px; }
    .text-decoration-line-through { opacity: 0.5; }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <a href="index.php" class="text-dark fs-4 me-3"><i class="bi bi-chevron-left"></i></a>
            <div>
                <h5 class="fw-bold mb-0 text-capitalize"><?= $titulo_mes ?></h5>
                <p class="text-muted small mb-0">Controle Bilateral de Fluxo</p>
            </div>
        </div>
        
        <div class="dropdown">
            <button class="btn btn-white border btn-sm rounded-pill dropdown-toggle px-3 shadow-sm" type="button" data-bs-toggle="dropdown">
                Filtrar Mês
            </button>
            <ul class="dropdown-menu shadow-lg border-0 rounded-3">
                <?php for($i = -3; $i <= 3; $i++): 
                    $m = date('Y-m', strtotime("+$i month", strtotime(date('Y-m-01'))));
                ?>
                <li><a class="dropdown-item" href="?mes=<?= $m ?>"><?= date('m/Y', strtotime($m)) ?></a></li>
                <?php endfor; ?>
            </ul>
        </div>
    </div>

    <?= $mensagem ?>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                <span class="small fw-bold text-success"><i class="bi bi-plus-circle-fill me-1"></i> RECEITAS</span>
                <span class="fw-bold text-success">R$ <?= number_format(array_sum(array_column($entradas, 'contavalor')), 2, ',', '.') ?></span>
            </div>
            
            <div class="card-bilateral border">
                <div class="list-group list-group-flush">
                    <?php if(empty($entradas)): ?>
                        <div class="p-5 text-center text-muted small">Nenhuma receita registrada.</div>
                    <?php else: foreach($entradas as $e): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="fw-bold d-block <?= $e['contasituacao'] == 'Pago' ? 'text-decoration-line-through text-muted' : '' ?>"><?= htmlspecialchars($e['contadescricao']) ?></span>
                                    <small class="text-muted"><?= $e['categoriadescricao'] ?? 'Geral' ?> • <?= date('d/m', strtotime($e['contavencimento'])) ?></small>
                                </div>
                                <span class="fw-bold text-success">R$ <?= number_format($e['contavalor'], 2, ',', '.') ?></span>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="acoes_conta.php?acao=<?= $e['contasituacao'] == 'Pendente' ? 'pagar' : 'estornar' ?>&id=<?= $e['contasid'] ?>" 
                                   class="btn btn-action <?= $e['contasituacao'] == 'Pendente' ? 'btn-success' : 'btn-light border' ?>">
                                   <?= $e['contasituacao'] == 'Pendente' ? 'RECEBER' : 'ESTORNAR' ?>
                                </a>
                                <button type="button" class="btn btn-light border btn-action" onclick='abrirModalEdicao(<?= json_encode($e) ?>)'>
                                    EDITAR
                                </button>
                                <button type="button" class="btn btn-light border btn-action text-danger" onclick="confirmarExclusao(<?= $e['contasid'] ?>)">
                                    EXCLUIR
                                </button>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                <span class="small fw-bold text-danger"><i class="bi bi-dash-circle-fill me-1"></i> DESPESAS</span>
                <span class="fw-bold text-danger">R$ <?= number_format(array_sum(array_column($saidas, 'contavalor')), 2, ',', '.') ?></span>
            </div>

            <div class="card-bilateral border">
                <div class="list-group list-group-flush">
                    <?php if(empty($saidas)): ?>
                        <div class="p-5 text-center text-muted small">Nenhuma despesa registrada.</div>
                    <?php else: foreach($saidas as $s): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="fw-bold d-block <?= $s['contasituacao'] == 'Pago' ? 'text-decoration-line-through text-muted' : '' ?>"><?= htmlspecialchars($s['contadescricao']) ?></span>
                                    <small class="text-muted"><?= $s['categoriadescricao'] ?? 'Geral' ?> • <?= date('d/m', strtotime($s['contavencimento'])) ?></small>
                                </div>
                                <span class="fw-bold text-danger">R$ <?= number_format($s['contavalor'], 2, ',', '.') ?></span>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="acoes_conta.php?acao=<?= $s['contasituacao'] == 'Pendente' ? 'pagar' : 'estornar' ?>&id=<?= $s['contasid'] ?>" 
                                   class="btn btn-action <?= $s['contasituacao'] == 'Pendente' ? 'btn-danger' : 'btn-light border' ?>">
                                   <?= $s['contasituacao'] == 'Pendente' ? 'PAGAR' : 'ESTORNAR' ?>
                                </a>
                                <button type="button" class="btn btn-light border btn-action" onclick='abrirModalEdicao(<?= json_encode($s) ?>)'>
                                    EDITAR
                                </button>
                                <button type="button" class="btn btn-light border btn-action text-danger" onclick="confirmarExclusao(<?= $s['contasid'] ?>)">
                                    EXCLUIR
                                </button>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarConta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h6 class="fw-bold mb-0">Editar Lançamento</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Descrição</label>
                        <input type="text" name="descricao" id="edit_descricao" class="form-control rounded-3 border-light bg-light py-2" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Valor (R$)</label>
                            <input type="decimal" inputmode="decimal" step="0.01" name="valor" id="edit_valor" class="form-control rounded-3 border-light bg-light py-2" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Vencimento</label>
                            <input type="date" name="vencimento" id="edit_vencimento" class="form-control rounded-3 border-light bg-light py-2" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow">Salvar Alterações</button>
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
    
    var myModal = new bootstrap.Modal(document.getElementById('modalEditarConta'));
    myModal.show();
}

function confirmarExclusao(id) {
    if (confirm('Deseja realmente excluir este lançamento?')) {
        window.location.href = `?mes=<?= $mes_filtro ?>&acao=excluir&id=${id}`;
    }
}
</script>

<?php require_once "../includes/footer.php"; ?>