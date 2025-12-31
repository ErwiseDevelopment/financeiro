<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$mensagem = "";

$data_alvo = new DateTime($mes_filtro . "-01");
$mes_atual_txt = $data_alvo->format('Y-m');

// --- LÓGICA DE EXCLUSÃO ---
if (isset($_GET['acao']) && $_GET['acao'] === 'excluir' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = $pdo->prepare("DELETE FROM contas WHERE contasid = ? AND usuarioid = ?");
    if ($sql->execute([$id, $uid])) {
        $mensagem = "<div class='alert alert-danger border-0 shadow-sm py-2 rounded-3 mb-4 text-center text-white bg-danger'>Lançamento excluído!</div>";
    }
}

// --- LÓGICA DE ATUALIZAÇÃO (EDIÇÃO) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'editar') {
    $id = $_POST['id'];
    $descricao = $_POST['descricao'];
    $valor = $_POST['valor'];
    $vencimento = $_POST['vencimento'];
    $cartoid = !empty($_POST['cartoid']) ? $_POST['cartoid'] : null;
    $contafixa = isset($_POST['contafixa']) ? 1 : 0;

    $data_obj = new DateTime($vencimento);
    $competencia_normal = $data_obj->format('Y-m');
    $competencia_fatura = null;

    if ($cartoid) {
        $stmt_c = $pdo->prepare("SELECT cartofechamento FROM cartoes WHERE cartoid = ?");
        $stmt_c->execute([$cartoid]);
        $fch = (int)$stmt_c->fetchColumn();
        $dia_compra = (int)$data_obj->format('d');
        
        $data_fatura = clone $data_obj;
        if ($dia_compra >= $fch) { 
            $data_fatura->modify('first day of next month'); 
        }
        $competencia_fatura = $data_fatura->format('Y-m');
    }

    $sql = $pdo->prepare("UPDATE contas SET contadescricao = ?, contavalor = ?, contavencimento = ?, contacompetencia = ?, competenciafatura = ?, cartoid = ?, contafixa = ? WHERE contasid = ? AND usuarioid = ?");
    if ($sql->execute([$descricao, $valor, $vencimento, $competencia_normal, $competencia_fatura, $cartoid, $contafixa, $id, $uid])) {
        $mensagem = "<div class='alert alert-success border-0 shadow-sm py-2 rounded-3 mb-4 text-center'>Atualizado com sucesso!</div>";
    }
}

// --- CONSULTAS ---
$stmt_cartoes = $pdo->prepare("SELECT * FROM cartoes WHERE usuarioid = ? ORDER BY cartonome ASC");
$stmt_cartoes->execute([$uid]);
$lista_cartoes = $stmt_cartoes->fetchAll();

// 1. Entradas
$stmt_entradas = $pdo->prepare("SELECT c.* FROM contas c WHERE c.usuarioid = ? AND c.contacompetencia = ? AND c.contatipo = 'Entrada' ORDER BY c.contavencimento ASC");
$stmt_entradas->execute([$uid, $mes_filtro]);
$entradas = $stmt_entradas->fetchAll();

// 2. Saídas Diretas
$stmt_saidas = $pdo->prepare("SELECT c.* FROM contas c WHERE c.usuarioid = ? AND c.contacompetencia = ? AND c.contatipo = 'Saída' AND c.cartoid IS NULL ORDER BY c.contavencimento ASC");
$stmt_saidas->execute([$uid, $mes_filtro]);
$saidas = $stmt_saidas->fetchAll();

// 3. Faturas (QUERY CORRIGIDA PARA VERIFICAR STATUS)
// Adicionei o SUM(CASE...) para contar quantos itens ainda estão como 'Pendente'
$stmt_faturas = $pdo->prepare("
    SELECT 
        car.cartonome, 
        car.cartoid, 
        car.cartovencimento, 
        SUM(c.contavalor) as total_fatura,
        SUM(CASE WHEN c.contasituacao = 'Pendente' THEN 1 ELSE 0 END) as qtd_pendentes
    FROM contas c 
    JOIN cartoes car ON c.cartoid = car.cartoid 
    WHERE c.usuarioid = ? 
    AND COALESCE(c.competenciafatura, c.contacompetencia) = ? 
    GROUP BY car.cartoid
");
$stmt_faturas->execute([$uid, $mes_atual_txt]);
$faturas_mes = $stmt_faturas->fetchAll();

// Totais
$total_entradas = array_sum(array_column($entradas, 'contavalor'));
$total_saidas_diretas = array_sum(array_column($saidas, 'contavalor'));
$total_faturas = array_sum(array_column($faturas_mes, 'total_fatura'));
$total_geral_saidas = $total_saidas_diretas + $total_faturas;
?>

<style>
    body { background-color: #f1f5f9; color: #1e293b; }
    .month-pill { white-space: nowrap; padding: 10px 22px; border-radius: 50px; background: #fff; color: #64748b; text-decoration: none; font-size: 0.85rem; border: 1px solid #e2e8f0; font-weight: 500; transition: 0.2s; }
    .month-pill:hover { background: #e2e8f0; }
    .month-pill.active { background: #0f172a; color: #fff; border-color: #0f172a; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    
    .card-main { border: none; border-radius: 24px; background: #fff; box-shadow: 0 4px 20px rgba(0,0,0,0.04); overflow: hidden; margin-bottom: 20px; }
    .list-item { border-bottom: 1px solid #f1f5f9; padding: 1.25rem; transition: 0.3s; }
    .list-item:last-child { border-bottom: none; }
    
    .desc-conta { color: #1e293b; font-size: 0.95rem; font-weight: 600; }
    .section-header { font-size: 0.75rem; font-weight: 700; color: #94a3b8; letter-spacing: 1.2px; text-transform: uppercase; }
    
    /* ESTILOS DE STATUS */
    .fatura-highlight { background: linear-gradient(to right, #ffffff, #f0f7ff); border-left: 5px solid #3b82f6 !important; }
    
    .status-pago { opacity: 0.6; background-color: #f8fafc; }
    .status-pago .desc-conta { text-decoration: line-through; color: #64748b; }
    .icon-check { display: none; color: #10b981; font-size: 1.2rem; }
    .status-pago .icon-check { display: inline-block; }
    
    .fatura-paga { background-color: #f0fdf4 !important; border-left: 5px solid #10b981 !important; opacity: 0.8; }

    .btn-action { font-weight: 600; font-size: 0.85rem; border-radius: 50px; padding: 6px 16px; transition: 0.2s; }
    .btn-recebido { border: 1px solid #d1d5db; color: #6b7280; background: transparent; }
    .btn-recebido:hover { background: #fee2e2; border-color: #ef4444; color: #ef4444; }
    .btn-recebido:hover::after { content: " (Estornar)"; font-size: 0.7em; }
</style>

<div class="container py-4">
    <div class="d-flex overflow-x-auto gap-2 mb-4 pb-2" style="scrollbar-width: none;">
        <?php for($i = -2; $i <= 4; $i++): 
            $m = date('Y-m', strtotime("+$i month", strtotime(date('Y-m-01'))));
            $label = (new IntlDateFormatter('pt_BR', 0, 0, null, null, 'MMMM yy'))->format(strtotime($m."-01"));
        ?>
            <a href="?mes=<?= $m ?>" class="month-pill <?= $mes_filtro == $m ? 'active' : '' ?>"><?= ucfirst($label) ?></a>
        <?php endfor; ?>
    </div>

    <?= $mensagem ?>

    <div class="row">
        <div class="col-lg-6">
            <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                <span class="section-header">Receitas</span>
                <span class="fw-bold text-success fs-5">R$ <?= number_format($total_entradas, 2, ',', '.') ?></span>
            </div>
            <div class="card-main">
                <?php if(empty($entradas)): ?> <p class="p-4 text-center text-muted">Nenhuma receita.</p> <?php endif; ?>
                <?php foreach($entradas as $e): $isPago = ($e['contasituacao'] == 'Pago'); ?>
                    <div class="list-item <?= $isPago ? 'status-pago' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-check-circle-fill icon-check"></i>
                                    <span class="desc-conta"><?= $e['contadescricao'] ?></span>
                                </div>
                                <small class="text-muted d-block">Venc: <?= date('d/m', strtotime($e['contavencimento'])) ?></small>
                            </div>
                            <span class="fw-bold text-success">R$ <?= number_format($e['contavalor'], 2, ',', '.') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mt-3 align-items-center">
                            <a href="acoes_conta.php?acao=<?= $isPago ? 'estornar' : 'pagar' ?>&id=<?= $e['contasid'] ?>&origem=fluxo" 
                               class="btn btn-sm btn-action <?= $isPago ? 'btn-recebido' : 'btn-success text-white shadow-sm' ?>">
                               <i class="bi <?= $isPago ? 'bi-check2-all' : 'bi-hand-thumbs-up' ?>"></i> <?= $isPago ? 'Recebido' : 'Receber' ?>
                            </a>
                            <div class="d-flex gap-2">
                                <button onclick='abrirModalEdicao(<?= json_encode($e) ?>)' class="btn btn-light btn-sm rounded-circle"><i class="bi bi-pencil"></i></button>
                                <a href="?mes=<?= $mes_filtro ?>&acao=excluir&id=<?= $e['contasid'] ?>" class="btn btn-light btn-sm rounded-circle text-danger" onclick="return confirm('Excluir?')"><i class="bi bi-trash"></i></a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                <span class="section-header">Despesas</span>
                <span class="fw-bold text-danger fs-5">R$ <?= number_format($total_geral_saidas, 2, ',', '.') ?></span>
            </div>
            <div class="card-main">
                
                <?php foreach($faturas_mes as $fat): 
                    // Se qtd_pendentes for 0, a fatura está paga
                    $faturaPaga = ($fat['qtd_pendentes'] == 0);
                ?>
                    <div class="list-item <?= $faturaPaga ? 'fatura-paga' : 'fatura-highlight' ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="desc-conta">
                                    <i class="bi <?= $faturaPaga ? 'bi-check-circle-fill text-success' : 'bi-credit-card-2-front' ?> me-1"></i> 
                                    Fatura <?= $fat['cartonome'] ?>
                                </span>
                                <?php if($faturaPaga): ?>
                                    <small class="text-success d-block fw-bold">PAGA</small>
                                <?php else: ?>
                                    <small class="text-primary d-block fw-bold">Vence dia <?= $fat['cartovencimento'] ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <span class="fw-bold <?= $faturaPaga ? 'text-success' : 'text-danger' ?> d-block">R$ <?= number_format($fat['total_fatura'], 2, ',', '.') ?></span>
                                <a href="faturas.php?cartoid=<?= $fat['cartoid'] ?>&mes=<?= $mes_filtro ?>" class="small text-decoration-none fw-bold">Ver Detalhes <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if(empty($saidas) && empty($faturas_mes)): ?> <p class="p-4 text-center text-muted">Nenhuma despesa.</p> <?php endif; ?>
                
                <?php foreach($saidas as $s): $isPago = ($s['contasituacao'] == 'Pago'); ?>
                    <div class="list-item <?= $isPago ? 'status-pago' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-check-circle-fill icon-check"></i>
                                    <span class="desc-conta"><?= $s['contadescricao'] ?></span>
                                </div>
                                <small class="text-muted d-block">Venc: <?= date('d/m', strtotime($s['contavencimento'])) ?></small>
                            </div>
                            <span class="fw-bold text-danger">R$ <?= number_format($s['contavalor'], 2, ',', '.') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mt-3 align-items-center">
                            <a href="acoes_conta.php?acao=<?= $isPago ? 'estornar' : 'pagar' ?>&id=<?= $s['contasid'] ?>&origem=fluxo" 
                               class="btn btn-sm btn-action <?= $isPago ? 'btn-recebido' : 'btn-danger text-white shadow-sm' ?>">
                               <i class="bi <?= $isPago ? 'bi-check2-all' : 'bi-wallet2' ?>"></i> <?= $isPago ? 'Pago' : 'Pagar' ?>
                            </a>
                            <div class="d-flex gap-2">
                                <button onclick='abrirModalEdicao(<?= json_encode($s) ?>)' class="btn btn-light btn-sm rounded-circle"><i class="bi bi-pencil"></i></button>
                                <a href="?mes=<?= $mes_filtro ?>&acao=excluir&id=<?= $s['contasid'] ?>" class="btn btn-light btn-sm rounded-circle text-danger" onclick="return confirm('Excluir?')"><i class="bi bi-trash"></i></a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarConta" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 25px;">
            <form method="POST">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body p-4">
                    <h5 class="fw-bold mb-4">Editar</h5>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Descrição</label>
                        <input type="text" name="descricao" id="edit_descricao" class="form-control" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6"><label class="form-label small fw-bold">Valor</label><input type="number" step="0.01" name="valor" id="edit_valor" class="form-control" required></div>
                        <div class="col-6"><label class="form-label small fw-bold">Data</label><input type="date" name="vencimento" id="edit_vencimento" class="form-control" required></div>
                    </div>
                    <div id="edit_div_cartao" class="mb-3">
                        <label class="form-label small fw-bold">Forma de Pagamento</label>
                        <select name="cartoid" id="edit_cartoid" class="form-select">
                            <option value="">Saldo (Dinheiro/Pix)</option>
                            <?php foreach($lista_cartoes as $c): ?><option value="<?= $c['cartoid'] ?>"><?= $c['cartonome'] ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="contafixa" id="edit_contafixa"><label class="form-check-label small fw-bold">Fixa Mensal</label></div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" class="btn btn-dark w-100 py-3 rounded-4 fw-bold">SALVAR</button>
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
    document.getElementById('edit_div_cartao').style.display = (conta.contatipo === 'Entrada') ? 'none' : 'block';
    new bootstrap.Modal(document.getElementById('modalEditarConta')).show();
}
</script>

<?php require_once "../includes/footer.php"; ?>