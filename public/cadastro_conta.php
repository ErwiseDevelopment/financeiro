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

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">

<style>
    :root {
        --app-bg: #f8fafc;
        --app-card: #ffffff;
        --app-text-main: #1e293b;
        --app-text-muted: #94a3b8;
    }

    body { background-color: var(--app-bg); color: var(--app-text-main); font-family: 'Inter', sans-serif; }
    .app-container { max-width: 800px; margin: 0 auto; padding: 20px; }

    /* Valor */
    .amount-container { padding: 40px 0; text-align: center; }
    #valor_display { font-size: 4rem; font-weight: 800; border: none; background: transparent; outline: none; width: 100%; text-align: center; color: var(--app-text-main); }

    /* Seletor Tipo */
    .type-picker { background: #e2e8f0; padding: 4px; border-radius: 16px; display: flex; gap: 4px; margin-bottom: 30px; max-width: 400px; margin: 0 auto 30px auto; }
    .type-picker .btn-check + .btn { flex: 1; border: none; border-radius: 12px; padding: 10px; font-weight: 600; color: #64748b; background: transparent; }
    .type-picker .btn-check:checked + .btn { background: #fff; color: var(--app-text-main); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }

    /* Cards e Inputs */
    .card-app { background: var(--app-card); border-radius: 28px; padding: 30px; box-shadow: 0 10px 25px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.05); height: 100%; }
    .label-app { font-size: 0.75rem; font-weight: 700; color: var(--app-text-muted); margin-bottom: 10px; display: block; text-transform: uppercase; }
    
    .input-app { 
        background-color: #f1f5f9 !important; 
        border: 2px solid transparent !important; 
        padding: 14px 16px !important; 
        border-radius: 16px !important; 
        font-size: 1rem;
    }

    /* ESTILO DO BUSCADOR (Tom Select) */
    .ts-wrapper .ts-control {
        background-color: #f1f5f9 !important;
        border: 2px solid transparent !important;
        border-radius: 16px !important;
        padding: 12px 16px !important;
        font-size: 1.1rem !important; /* TEXTO GRANDE */
        font-weight: 600 !important;
        min-height: 54px;
        display: flex;
        align-items: center;
    }
    .ts-wrapper.single .ts-control:after { border-color: #334155 transparent transparent transparent; margin-top: 0; }
    .ts-dropdown { border-radius: 16px !important; border: none !important; box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important; padding: 8px; }
    .ts-dropdown .option { padding: 12px 16px !important; border-radius: 8px; font-size: 1rem; }
    .ts-dropdown .active { background-color: #e2e8f0 !important; color: #000 !important; }

    .switch-container { background: #f1f5f9; padding: 18px; border-radius: 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; cursor: pointer; }

    .action-buttons { display: flex; gap: 15px; margin-top: 20px; }
    .btn-confirm { background: #1e293b; color: #fff; border-radius: 20px; padding: 18px; font-weight: 700; border: none; flex: 2; }
    .btn-add-more { background: #fff; color: #1e293b; border: 2px solid #e2e8f0; border-radius: 20px; padding: 18px; font-weight: 700; flex: 1; }
</style>

<div class="app-container">
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
                        <label class="label-app">Descrição</label>
                        <input type="text" name="contadescricao" class="form-control input-app" placeholder="Ex: Salário..." required>
                    </div>

                    <div class="mb-4">
                        <label class="label-app">Categoria</label>
                        <div class="d-flex gap-2">
                            <div style="flex: 1;">
                                <select id="selectCategoria" name="categoriaid" placeholder="Buscar categoria..." autocomplete="off">
                                    <option value="">Buscar categoria...</option>
                                    <?php foreach($categorias as $cat): 
                                        $tipo_banco = ($cat['categoriatipo'] == 'Receita') ? 'Entrada' : 'Saída';
                                    ?>
                                        <option value="<?= $cat['categoriaid'] ?>" data-tipo="<?= $tipo_banco ?>" <?= ($cat['categoriaid'] == $cat_pre) ? 'selected' : '' ?>>
                                            <?= $cat['categoriadescricao'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="btn input-app px-3" data-bs-toggle="modal" data-bs-target="#modalRapidoCategoria" style="height: 54px;">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                    </div>

                    <div id="divCartao" style="<?= $tipo_pre == 'Entrada' ? 'display:none;' : '' ?>">
                        <label class="label-app">Pagamento via</label>
                        <select name="cartoid" class="form-select input-app">
                            <option value="">Saldo em Conta</option>
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
                <div class="card-app">
                    <div class="switch-container" onclick="document.getElementById('checkFixa').click();">
                        <span class="fw-bold small">Repetir mensal?</span>
                        <div class="form-check form-switch">
                            <input class="form-check-input fs-4" type="checkbox" name="contafixa" value="1" id="checkFixa" <?= ($fixa_pre == 1) ? 'checked' : '' ?>>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="label-app">Vencimento</label>
                        <input type="date" name="contavencimento" class="form-control input-app" value="<?= $venc_pre ?>" required>
                    </div>
                    <div>
                        <label class="label-app">Parcelas</label>
                        <input type="number" name="contaparcela_total" class="form-control input-app text-center" value="1" min="1">
                    </div>
                </div>
            </div>
        </div>

        <div class="action-buttons pb-5">
            <button type="submit" onclick="document.getElementById('manter_dados').value='1'" class="btn-add-more">Salvar e Novo</button>
            <button type="submit" onclick="document.getElementById('manter_dados').value='0'" class="btn-confirm">Confirmar</button>
        </div>
    </form>
</div>

<div class="modal fade" id="modalRapidoCategoria" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 350px;">
        <div class="modal-content border-0 shadow" style="border-radius: 24px;">
            <div class="modal-header border-0 pb-0">
                <h6 class="fw-bold m-0">Nova Categoria</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="label-app">Nome da Categoria</label>
                    <input type="text" id="nome_nova_categoria" class="form-control input-app" placeholder="Ex: Mercado, Freelance...">
                </div>
                
                <div class="mb-4">
                    <label class="label-app">Tipo</label>
                    <select id="tipo_nova_categoria" class="form-select input-app">
                        <option value="Saída">Despesa (Saída)</option>
                        <option value="Entrada">Receita (Entrada)</option>
                    </select>
                </div>
                
                <button type="button" onclick="salvarCategoriaRapida()" class="btn-confirm w-100 shadow-sm">
                    Salvar Categoria
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

<script>
    let tomControl;

    // Inicialização Segura
    window.addEventListener('DOMContentLoaded', () => {
        const selectElement = document.getElementById('selectCategoria');
        
        // Verifica se a biblioteca TomSelect existe
        if (typeof TomSelect !== "undefined") {
            tomControl = new TomSelect(selectElement, {
                create: false,
                sortField: { field: "text", direction: "asc" },
                allowEmptyOption: true
            });
            filtrarCategorias();
        } else {
            console.error("TomSelect não carregou via CDN");
        }
    });

    // Filtro Dinâmico
    function filtrarCategorias() {
        if (!tomControl) return;

        const tipoSelecionado = document.querySelector('input[name="contatipo"]:checked').value;
        const valorAtual = tomControl.getValue();
        
        // Dados originais do PHP
        const categoriasJson = <?= json_encode(array_map(function($c) {
            return [
                'id' => $c['categoriaid'],
                'text' => $c['categoriadescricao'],
                'tipo' => ($c['categoriatipo'] == 'Receita') ? 'Entrada' : 'Saída'
            ];
        }, $categorias)) ?>;

        tomControl.clearOptions();
        
        categoriasJson.forEach(cat => {
            if (cat.tipo === tipoSelecionado) {
                tomControl.addOption({value: cat.id, text: cat.text});
            }
        });

        // Só mantém o valor se ele for do tipo certo
        const existeNoNovoTipo = categoriasJson.find(c => c.id == valorAtual && c.tipo == tipoSelecionado);
        if (existeNoNovoTipo) {
            tomControl.setValue(valorAtual);
        } else {
            tomControl.clear();
        }

        tomControl.refreshOptions(false);
    }

    // Máscara de Moeda
    document.getElementById('valor_display').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, "");
        value = (value / 100).toFixed(2);
        e.target.value = value == "0.00" ? "" : value.replace(".", ",").replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
        document.getElementById('valor_real').value = value;
    });

    // Eventos de Tipo
    document.querySelectorAll('input[name="contatipo"]').forEach(r => {
        r.addEventListener('change', () => {
            document.getElementById('divCartao').style.display = (r.value === 'Entrada') ? 'none' : 'block';
            filtrarCategorias();
        });
    });

   function salvarCategoriaRapida() {
    const nome = document.getElementById('nome_nova_categoria').value;
    const tipoSelecionadoNoModal = document.getElementById('tipo_nova_categoria').value; 
    // Captura "Entrada" ou "Saída" do select do modal

    if (!nome) {
        alert("Por favor, digite o nome da categoria.");
        return;
    }

    const formData = new FormData();
    formData.append('categoriadescricao', nome);
    formData.append('categoriatipo', tipoSelecionadoNoModal);

    fetch('ajax_rapido_categoria.php', { 
        method: 'POST', 
        body: formData 
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            // 1. Adiciona a opção no buscador (TomSelect)
            tomControl.addOption({
                value: data.id, 
                text: data.nome,
                tipo: tipoSelecionadoNoModal // Armazena o tipo para o filtro funcionar
            });

            // 2. Fecha o modal
            const modalEl = document.getElementById('modalRapidoCategoria');
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            modalInstance.hide();

            // 3. Limpa o campo do modal
            document.getElementById('nome_nova_categoria').value = "";

            // 4. Se a categoria criada for do mesmo tipo que o formulário está exibindo, seleciona ela
            const tipoFormulario = document.querySelector('input[name="contatipo"]:checked').value;
            if (tipoSelecionadoNoModal === tipoFormulario) {
                tomControl.setValue(data.id);
            } else {
                alert("Categoria salva! Como ela é de um tipo diferente do lançamento atual, ela ficará disponível quando você alternar entre Receita/Despesa.");
            }

        } else {
            alert(data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert("Erro ao salvar categoria.");
    });
}
</script>

<?php require_once "../includes/footer.php"; ?>