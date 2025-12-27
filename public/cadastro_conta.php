<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];
$tipo_pre = $_GET['tipo'] ?? 'Saída';

// Busca categorias
$stmt_cat = $pdo->prepare("SELECT * FROM categorias WHERE usuarioid = ? ORDER BY categoriadescricao ASC");
$stmt_cat->execute([$uid]);
$categorias = $stmt_cat->fetchAll();

// Busca cartões do usuário
$stmt_cartoes = $pdo->prepare("SELECT cartoid, cartonome FROM cartoes WHERE usuarioid = ? ORDER BY cartonome ASC");
$stmt_cartoes->execute([$uid]);
$cartoes = $stmt_cartoes->fetchAll();
?>

<style>
    .form-label-caps { font-size: 0.65rem; font-weight: 800; color: #94a3b8; letter-spacing: 1px; display: block; margin-bottom: 8px; }
    .btn-type-select { border: 2px solid transparent; background: #f1f5f9; color: #64748b; transition: 0.3s; }
    .btn-check:checked + .btn-type-select { background: #fff; border-color: var(--bs-primary); color: var(--bs-primary); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .form-control-flush:focus { outline: none; }
    .switch-fixa-container { cursor: pointer; transition: 0.2s; border: 1px solid transparent; }
    .switch-fixa-container:hover { border-color: #e2e8f0; }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 px-1">
        <a href="index.php" class="text-dark fs-4 text-decoration-none"><i class="bi bi-chevron-left"></i></a>
        <h5 class="fw-bold mb-0">Novo Lançamento</h5>
        <div style="width: 24px;"></div>
    </div>

    <form action="salvar_conta.php" method="POST" id="formLancamento">
        <div class="mb-4">
            <div class="btn-group w-100 p-1 bg-white border rounded-4 shadow-sm" role="group">
                <input type="radio" class="btn-check" name="contatipo" id="entrada" value="Entrada" <?= $tipo_pre == 'Entrada' ? 'checked' : '' ?>>
                <label class="btn btn-type-select py-2 rounded-4 fw-bold" for="entrada">
                    <i class="bi bi-plus-circle-fill me-1"></i> Entrada
                </label>

                <input type="radio" class="btn-check" name="contatipo" id="saida" value="Saída" <?= $tipo_pre == 'Saída' ? 'checked' : '' ?>>
                <label class="btn btn-type-select py-2 rounded-4 fw-bold" for="saida">
                    <i class="bi bi-dash-circle-fill me-1"></i> Saída
                </label>
            </div>
        </div>

        <div class="text-center mb-5">
            <span class="form-label-caps">VALOR DO LANÇAMENTO</span>
            <div class="d-flex justify-content-center align-items-baseline">
                <span class="fs-4 fw-bold text-muted me-2">R$</span>
                <input type="text" id="valor_display" inputmode="decimal" class="form-control-flush fs-1 fw-bold text-center w-75 bg-transparent border-0" placeholder="0,00" required autofocus>
                <input type="hidden" name="contavalor" id="valor_real">
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
            <div class="mb-4">
                <span class="form-label-caps">DESCRIÇÃO</span>
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="bi bi-chat-left-text"></i></span>
                    <input type="text" name="contadescricao" class="form-control bg-light border-0 p-3" placeholder="Ex: Gasolina, Internet..." required>
                </div>
            </div>

            <div class="mb-4">
                <span class="form-label-caps">CATEGORIA</span>
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="bi bi-grid"></i></span>
                    <select name="categoriaid" id="selectCategoria" class="form-select bg-light border-0 p-3" required>
                        <option value="">Selecione...</option>
                        <?php foreach($categorias as $cat): ?>
                            <option value="<?= $cat['categoriaid'] ?>"><?= $cat['categoriadescricao'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-light border-0 px-3" data-bs-toggle="modal" data-bs-target="#modalRapidoCategoria">
                        <i class="bi bi-plus-circle-fill text-primary"></i>
                    </button>
                </div>
            </div>

            <div id="divCartao" class="mb-4" style="<?= $tipo_pre == 'Entrada' ? 'display:none;' : '' ?>">
                <span class="form-label-caps">PAGAR COM CARTÃO?</span>
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="bi bi-credit-card-2-back"></i></span>
                    <select name="cartoid" class="form-select bg-light border-0 p-3">
                        <option value="">Não (Dinheiro/Débito)</option>
                        <?php foreach($cartoes as $cartao): ?>
                            <option value="<?= $cartao['cartoid'] ?>"><?= $cartao['cartonome'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-4 p-3 rounded-4 bg-light d-flex justify-content-between align-items-center switch-fixa-container" onclick="document.getElementById('checkFixa').click();">
                <div>
                    <span class="fw-bold d-block small">Marcar como Conta Fixa?</span>
                    <small class="text-muted" style="font-size: 0.65rem;">Ela aparecerá pronta para copiar no próximo mês.</small>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input fs-4" type="checkbox" name="contafixa" value="1" id="checkFixa" onclick="event.stopPropagation();">
                </div>
            </div>

            <div class="row g-3">
                <div class="col-6">
                    <span class="form-label-caps">DATA</span>
                    <input type="date" name="contavencimento" class="form-control bg-light border-0 p-3" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-6">
                    <span class="form-label-caps">PARCELAS</span>
                    <div class="input-group">
                        <input type="number" inputmode="numeric" name="contaparcela_total" class="form-control bg-light border-0 p-3 text-center" value="1" min="1">
                        <span class="input-group-text bg-light border-0 small">x</span>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-3 rounded-4 fw-bold shadow-lg border-0">
            Confirmar Lançamento
        </button>
    </form>
</div>

<div class="modal fade" id="modalRapidoCategoria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered px-4">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0">
                <h6 class="fw-bold m-0">Nova Categoria</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="closeModalCat"></button>
            </div>
            <div class="modal-body pt-0">
                <input type="text" id="nome_nova_categoria" class="form-control bg-light border-0 p-3 mb-3" placeholder="Nome da categoria (ex: Lazer)" onkeypress="if(event.key === 'Enter') { salvarCategoriaRapida(); event.preventDefault(); }">
                <button type="button" onclick="salvarCategoriaRapida()" class="btn btn-primary w-100 py-2 rounded-3 fw-bold">
                    Salvar e Selecionar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const inputDisplay = document.getElementById('valor_display');
    const inputReal = document.getElementById('valor_real');
    const divCartao = document.getElementById('divCartao');
    const radioEntrada = document.getElementById('entrada');
    const radioSaida = document.getElementById('saida');
    const inputParcelas = document.querySelector('input[name="contaparcela_total"]');

    // Alternar visibilidade do campo cartão
    radioEntrada.addEventListener('change', () => divCartao.style.display = 'none');
    radioSaida.addEventListener('change', () => divCartao.style.display = 'block');

    // Máscara de Moeda (R$)
    inputDisplay.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, "");
        value = (value / 100).toFixed(2) + "";
        let display = value.replace(".", ",").replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
        e.target.value = display;
        inputReal.value = value;
        atualizarPreviewParcela();
    });

    // Preview do valor das parcelas
    function atualizarPreviewParcela() {
        const total = parseFloat(inputReal.value);
        const qtd = parseInt(inputParcelas.value);
        let info = document.getElementById('info-parcela');
        
        if (qtd > 1 && total > 0) {
            const valorParcela = (total / qtd).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            if(!info) {
                info = document.createElement('small');
                info.id = 'info-parcela';
                info.className = 'text-primary fw-bold d-block mt-1';
                inputParcelas.parentElement.after(info);
            }
            info.innerHTML = `${qtd}x de ${valorParcela}`;
        } else if(info) info.remove();
    }

    inputParcelas.addEventListener('input', atualizarPreviewParcela);

    // Salvar Categoria via AJAX
    function salvarCategoriaRapida() {
        const nome = document.getElementById('nome_nova_categoria').value;
        const tipo = document.querySelector('input[name="contatipo"]:checked').value;

        if (!nome) { alert("Digite o nome da categoria"); return; }

        const formData = new FormData();
        formData.append('categoriadescricao', nome);
        formData.append('categoriatipo', tipo);

        fetch('ajax_rapido_categoria.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const select = document.getElementById('selectCategoria');
                select.add(new Option(data.nome, data.id));
                select.value = data.id;
                document.getElementById('nome_nova_categoria').value = "";
                document.getElementById('closeModalCat').click();
            } else {
                alert("Erro: " + data.message);
            }
        });
    }

    // Fallback para valor zero no submit
    document.getElementById('formLancamento').addEventListener('submit', function() {
        if (!inputReal.value) inputReal.value = "0.00";
    });
</script>

<?php require_once "../includes/footer.php"; ?>