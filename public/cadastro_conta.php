<?php
require_once "../config/database.php";
require_once "../includes/header.php";

$uid = $_SESSION['usuarioid'];

// Memória do Formulário
$tipo_pre = $_GET['tipo'] ?? 'Saída';
$cat_pre  = $_GET['cat'] ?? '';
$car_pre  = $_GET['car'] ?? '';
$venc_pre = $_GET['venc'] ?? date('Y-m-d');
$fixa_pre = $_GET['fixa'] ?? 0;

// Consultas
$stmt_cat = $pdo->prepare("SELECT * FROM categorias WHERE usuarioid = ? ORDER BY categoriadescricao ASC");
$stmt_cat->execute([$uid]);
$categorias = $stmt_cat->fetchAll();

$stmt_cartoes = $pdo->prepare("SELECT cartoid, cartonome FROM cartoes WHERE usuarioid = ? ORDER BY cartonome ASC");
$stmt_cartoes->execute([$uid]);
$cartoes = $stmt_cartoes->fetchAll();
?>

<style>
    :root {
        --app-bg: #f8fafc;
        --app-card: #ffffff;
        --app-text-main: #1e293b;
        --app-text-muted: #94a3b8;
        --app-primary: #334155;
    }

    body { background-color: var(--app-bg); color: var(--app-text-main); font-family: 'Inter', system-ui, -apple-system, sans-serif; }

    /* Ajuste de Container para Computador */
    .app-container { 
        max-width: 800px; /* Aumentado para preencher melhor o desktop */
        margin: 0 auto;
        padding: 20px;
    }

    /* Valor Principal */
    .amount-container { padding: 40px 0; text-align: center; }
    .amount-label { font-size: 0.75rem; font-weight: 700; color: var(--app-text-muted); letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 8px; display: block; }
    #valor_display { font-size: 4rem; font-weight: 800; border: none; background: transparent; outline: none; width: 100%; text-align: center; color: var(--app-text-main); letter-spacing: -2px; }

    /* Seletor Tipo */
    .type-picker { background: #e2e8f0; padding: 4px; border-radius: 16px; display: flex; gap: 4px; margin-bottom: 30px; max-width: 400px; margin-left: auto; margin-right: auto; }
    .type-picker .btn-check + .btn { flex: 1; border: none; border-radius: 12px; padding: 10px; font-weight: 600; color: #64748b; background: transparent; transition: 0.2s; }
    .type-picker .btn-check:checked + .btn { background: #fff; color: var(--app-text-main); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }

    /* Estilo dos Cards */
    .card-app { background: var(--app-card); border-radius: 28px; padding: 30px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.05); height: 100%; }
    .label-app { font-size: 0.75rem; font-weight: 700; color: var(--app-text-muted); margin-bottom: 10px; display: block; text-transform: uppercase; }
    
    .input-app { 
        background-color: #f1f5f9 !important; 
        border: 2px solid transparent !important; 
        padding: 14px 16px !important; 
        border-radius: 16px !important; 
        transition: 0.2s;
    }
    .input-app:focus { border-color: #cbd5e1 !important; background-color: #fff !important; box-shadow: none !important; }

    /* Switch */
    .switch-container { background: #f1f5f9; padding: 18px; border-radius: 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; cursor: pointer; }

    /* Rodapé de Botões Fixos no Mobile / Lado a Lado no PC */
    .action-buttons { display: flex; gap: 15px; margin-top: 20px; }
    .btn-confirm { background: var(--app-text-main); color: #fff; border-radius: 20px; padding: 18px; font-weight: 700; border: none; flex: 2; transition: 0.3s; }
    .btn-add-more { background: #fff; color: var(--app-text-main); border: 2px solid #e2e8f0; border-radius: 20px; padding: 18px; font-weight: 700; flex: 1; transition: 0.3s; }

    @media (max-width: 576px) {
        #valor_display { font-size: 3rem; }
        .card-app { padding: 20px; }
        .action-buttons { flex-direction: column-reverse; } /* Inverte para o principal ficar em cima no mobile */
    }
</style>

<div class="app-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="index.php" class="text-dark"><i class="bi bi-x-lg fs-4"></i></a>
        <h5 class="fw-bold mb-0">Novo Lançamento</h5>
        <div style="width: 32px;"></div>
    </div>

    <form action="salvar_conta.php" method="POST" id="formLancamento">
        <input type="hidden" name="manter_dados" id="manter_dados" value="0">

        <div class="amount-container">
            <span class="amount-label">Valor do Registro</span>
            <div class="d-flex justify-content-center align-items-baseline">
                <span class="fs-2 fw-bold text-muted me-2">R$</span>
                <input type="text" id="valor_display" inputmode="decimal" placeholder="0,00" required autofocus>
                <input type="hidden" name="contavalor" id="valor_real">
            </div>
        </div>

        <div class="type-picker">
            <input type="radio" class="btn-check" name="contatipo" id="entrada" value="Entrada" <?= $tipo_pre == 'Entrada' ? 'checked' : '' ?>>
            <label class="btn" for="entrada">Receita</label>

            <input type="radio" class="btn-check" name="contatipo" id="saida" value="Saída" <?= $tipo_pre == 'Saída' ? 'checked' : '' ?>>
            <label class="btn" for="saida">Despesa</label>
        </div>

        <div class="row g-4">
            <div class="col-md-7">
                <div class="card-app">
                    <div class="mb-4">
                        <label class="label-app">O que é?</label>
                        <input type="text" name="contadescricao" class="form-control input-app" placeholder="Ex: Assinatura Netflix" required>
                    </div>

                    <div class="mb-4">
                        <label class="label-app">Categoria</label>
                        <div class="d-flex gap-2">
                            <select name="categoriaid" id="selectCategoria" class="form-select input-app" required>
                                <option value="">Selecione...</option>
                                <?php foreach($categorias as $cat): ?>
                                    <option value="<?= $cat['categoriaid'] ?>" <?= ($cat['categoriaid'] == $cat_pre) ? 'selected' : '' ?>>
                                        <?= $cat['categoriadescricao'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn input-app px-3" data-bs-toggle="modal" data-bs-target="#modalRapidoCategoria">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                    </div>

                    <div id="divCartao" style="<?= $tipo_pre == 'Entrada' ? 'display:none;' : '' ?>">
                        <label class="label-app">Pagamento via</label>
                        <select name="cartoid" class="form-select input-app">
                            <option value="">Saldo em Conta (Débito/Pix)</option>
                            <?php foreach($cartoes as $cartao): ?>
                                <option value="<?= $cartao['cartoid'] ?>" <?= ($cartao['cartoid'] == $car_pre) ? 'selected' : '' ?>>
                                     💳 <?= $cartao['cartonome'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="card-app d-flex flex-column justify-content-between">
                    <div>
                        <div class="switch-container" onclick="document.getElementById('checkFixa').click();">
                            <div>
                                <span class="d-block fw-bold small">Repetir mensal?</span>
                                <small class="text-muted" style="font-size: 0.7rem;">Lançamento Fixo</small>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input fs-4" type="checkbox" name="contafixa" value="1" id="checkFixa" <?= ($fixa_pre == 1) ? 'checked' : '' ?> onclick="event.stopPropagation();">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="label-app">Data de Vencimento</label>
                            <input type="date" name="contavencimento" class="form-control input-app" value="<?= $venc_pre ?>" required>
                        </div>

                        <div>
                            <label class="label-app">Número de Parcelas</label>
                            <input type="number" name="contaparcela_total" class="form-control input-app text-center" value="1" min="1">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="action-buttons pb-5">
            <button type="submit" onclick="document.getElementById('manter_dados').value='1'" class="btn-add-more shadow-sm">
                Salvar e Novo
            </button>
            <button type="submit" onclick="document.getElementById('manter_dados').value='0'" class="btn-confirm shadow">
                Confirmar Lançamento
            </button>
        </div>
    </form>
</div>

<div class="modal fade" id="modalRapidoCategoria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered px-4" style="max-width: 400px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px;">
            <div class="modal-header border-0 pb-0">
                <h6 class="fw-bold m-0">Nova Categoria</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="closeModalCat"></button>
            </div>
            <div class="modal-body">
                <label class="label-app">Nome da Categoria</label>
                <input type="text" id="nome_nova_categoria" class="form-control input-app mb-3" placeholder="Ex: Assinaturas, Lazer..." onkeypress="if(event.key === 'Enter') { salvarCategoriaRapida(); event.preventDefault(); }">
                
                <button type="button" onclick="salvarCategoriaRapida()" class="btn-confirm w-100 border-0">
                    Salvar e Selecionar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Máscara de Moeda
    const inputDisplay = document.getElementById('valor_display');
    const inputReal = document.getElementById('valor_real');
    
    inputDisplay.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, "");
        value = (value / 100).toFixed(2) + "";
        let display = value.replace(".", ",").replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
        e.target.value = display === "0,00" ? "" : display;
        inputReal.value = value;
    });

    // Toggle Cartão
    document.querySelectorAll('input[name="contatipo"]').forEach(radio => {
        radio.addEventListener('change', (e) => {
            document.getElementById('divCartao').style.display = e.target.value === 'Entrada' ? 'none' : 'block';
        });
    });

    function salvarCategoriaRapida() {
    const nome = document.getElementById('nome_nova_categoria').value;
    const tipo = document.querySelector('input[name="contatipo"]:checked').value;

    if (!nome) { 
        alert("Por favor, digite o nome da categoria."); 
        return; 
    }

    const formData = new FormData();
    formData.append('categoriadescricao', nome);
    formData.append('categoriatipo', tipo);

    fetch('ajax_rapido_categoria.php', { 
        method: 'POST', 
        body: formData 
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            const select = document.getElementById('selectCategoria');
            // Adiciona a nova opção ao select e seleciona
            const novaOpcao = new Option(data.nome, data.id);
            select.add(novaOpcao);
            select.value = data.id;
            
            // Limpa e fecha
            document.getElementById('nome_nova_categoria').value = "";
            const modalElement = document.getElementById('modalRapidoCategoria');
            const modalInstance = bootstrap.Modal.getInstance(modalElement);
            modalInstance.hide();
        } else {
            alert("Erro: " + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert("Erro ao salvar categoria.");
    });
}
</script>

<?php require_once "../includes/footer.php"; ?>