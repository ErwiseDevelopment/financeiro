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

$stmt_cartoes = $pdo->prepare("SELECT cartoid, cartonome, cartofechamento FROM cartoes WHERE usuarioid = ? ORDER BY cartonome ASC");
$stmt_cartoes->execute([$uid]);
$cartoes = $stmt_cartoes->fetchAll();
?>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">

<style>
    :root { --app-bg: #f8fafc; --app-card: #ffffff; --app-text-main: #1e293b; --app-text-muted: #94a3b8; }
    body { background-color: var(--app-bg); color: var(--app-text-main); font-family: 'Plus Jakarta Sans', sans-serif; }
    .app-container { max-width: 800px; margin: 0 auto; padding: 20px; }

    /* Valor */
    .amount-container { padding: 40px 0; text-align: center; }
    #valor_display { font-size: 4rem; font-weight: 800; border: none; background: transparent; outline: none; width: 100%; text-align: center; color: var(--app-text-main); }
    .amount-label { font-weight: 600; color: #94a3b8; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }

    /* Tipo */
    .type-picker { background: #e2e8f0; padding: 4px; border-radius: 16px; display: flex; gap: 4px; margin-bottom: 30px; max-width: 400px; margin: 0 auto 30px auto; }
    .type-picker .btn-check + .btn { flex: 1; border: none; border-radius: 12px; padding: 10px; font-weight: 600; color: #64748b; background: transparent; }
    .type-picker .btn-check:checked + .btn { background: #fff; color: var(--app-text-main); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }

    /* Cards e Inputs */
    .card-app { background: var(--app-card); border-radius: 28px; padding: 30px; box-shadow: 0 10px 25px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.05); height: 100%; }
    .label-app { font-size: 0.75rem; font-weight: 700; color: var(--app-text-muted); margin-bottom: 10px; display: block; text-transform: uppercase; }
    .input-app { background-color: #f1f5f9 !important; border: 2px solid transparent !important; padding: 14px 16px !important; border-radius: 16px !important; font-size: 1rem; font-weight: 600; color: #334155; }
    .input-app:focus { background: #fff !important; border-color: #4361ee !important; box-shadow: 0 0 0 4px rgba(67,97,238,0.1); }

    /* Tom Select */
    .ts-wrapper .ts-control { background-color: #f1f5f9 !important; border: 2px solid transparent !important; border-radius: 16px !important; padding: 12px 16px !important; font-size: 1.1rem !important; font-weight: 600 !important; min-height: 54px; display: flex; align-items: center; }
    .ts-dropdown { border-radius: 16px !important; border: none !important; box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important; padding: 8px; }
    .ts-dropdown .option { padding: 12px 16px !important; border-radius: 8px; font-size: 1rem; }
    .ts-dropdown .active { background-color: #e2e8f0 !important; color: #000 !important; }

    /* Ações */
    .switch-container { background: #f1f5f9; padding: 18px; border-radius: 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; cursor: pointer; }
    .action-buttons { display: flex; gap: 15px; margin-top: 20px; }
    .btn-confirm { background: #1e293b; color: #fff; border-radius: 20px; padding: 18px; font-weight: 700; border: none; flex: 2; transition: 0.2s; }
    .btn-confirm:hover { background: #0f172a; transform: translateY(-2px); }
    .btn-add-more { background: #fff; color: #1e293b; border: 2px solid #e2e8f0; border-radius: 20px; padding: 18px; font-weight: 700; flex: 1; transition: 0.2s; }
    .btn-add-more:hover { border-color: #cbd5e1; background: #f8fafc; }
    .badge-fatura { background-color: #dbeafe; color: #1e40af; padding: 6px 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; margin-top: 8px; display: inline-block; }

    /* --- FEEDBACK META --- */
    #feedbackMeta { display: none; margin-top: 20px; background: #fff; border: 2px solid #f1f5f9; border-radius: 16px; padding: 15px; animation: fadeIn 0.4s ease; }
    .meta-progress-bg { height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden; margin: 8px 0; }
    .meta-progress-fill { height: 100%; transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1); }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
    .text-alert-danger { color: #ef4444; font-weight: 800; }
    .text-alert-warning { color: #f59e0b; font-weight: 800; }
    .text-alert-success { color: #10b981; font-weight: 800; }
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
                                    <option value="">Selecione...</option>
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
                        <div id="feedbackMeta"></div>
                    </div>

                    <div id="divCartao" style="<?= $tipo_pre == 'Entrada' ? 'display:none;' : '' ?>">
                        <label class="label-app">Forma de Pagamento</label>
                        <select name="cartoid" id="selectCartao" class="form-select input-app">
                            <option value="" data-fechamento="0">Saldo em Conta / Débito</option>
                            <?php foreach($cartoes as $cartao): ?>
                                <option value="<?= $cartao['cartoid'] ?>" data-fechamento="<?= $cartao['cartofechamento'] ?>" <?= ($cartao['cartoid'] == $car_pre) ? 'selected' : '' ?>>
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
                        <span class="fw-bold small">Despesa Mensal Fixa?</span>
                        <div class="form-check form-switch">
                            <input class="form-check-input fs-4" type="checkbox" name="contafixa" value="1" id="checkFixa" <?= ($fixa_pre == 1) ? 'checked' : '' ?>>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="label-app" id="labelData">Data</label>
                        <input type="date" name="contavencimento" id="contavencimento" class="form-control input-app" value="<?= $venc_pre ?>" required>
                        <div id="feedbackFatura" class="fade-in mt-2" style="display:none;"></div>
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
            <button type="submit" onclick="document.getElementById('manter_dados').value='0'" class="btn-confirm">Confirmar Lançamento</button>
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
                    <label class="label-app">Nome</label>
                    <input type="text" id="nome_nova_categoria" class="form-control input-app" placeholder="Ex: Mercado...">
                </div>
                <div class="mb-4">
                    <label class="label-app">Tipo</label>
                    <select id="tipo_nova_categoria" class="form-select input-app">
                        <option value="Saída">Despesa</option>
                        <option value="Entrada">Receita</option>
                    </select>
                </div>
                <button type="button" onclick="salvarCategoriaRapida()" class="btn-confirm w-100 shadow-sm py-3">Salvar</button>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

<script>
    let tomControl;
    const selectCartao = document.getElementById('selectCartao');
    const inputData = document.getElementById('contavencimento');
    const labelData = document.getElementById('labelData');
    const feedbackFatura = document.getElementById('feedbackFatura');
    const boxMeta = document.getElementById('feedbackMeta');

    window.addEventListener('DOMContentLoaded', () => {
        // Inicializa o seletor de Categoria
        const selectElement = document.getElementById('selectCategoria');
        if (typeof TomSelect !== "undefined") {
            tomControl = new TomSelect(selectElement, {
                create: false, 
                sortField: { field: "text", direction: "asc" }, 
                allowEmptyOption: true,
                onChange: function(value) {
                    // Ao selecionar categoria, dispara a verificação
                    verificarMeta();
                }
            });
            // Tenta pré-selecionar se vier do PHP
            filtrarCategorias();
        }
        
        // Verifica data inicial (se estiver preenchida via PHP)
        verificarPrevisaoFatura();
        
        // Se já tiver categoria selecionada no load, verifica meta
        if(document.getElementById('selectCategoria').value) {
            verificarMeta();
        }
    });

    // --- FUNÇÃO PRINCIPAL: VERIFICAR META ---
    function verificarMeta() {
        const catId = tomControl ? tomControl.getValue() : '';
        const cartaoId = selectCartao.value;
        const tipoConta = document.querySelector('input[name="contatipo"]:checked').value;

        // Pega a data do campo. Se estiver vazia, usa a data de HOJE para a projeção
        let dataVal = inputData.value;
        if (!dataVal) {
            const hoje = new Date();
            // Formata para YYYY-MM-DD
            dataVal = hoje.toISOString().split('T')[0];
        }

        // Se for Entrada ou não tiver categoria, esconde e sai
        if (tipoConta === 'Entrada' || !catId) {
            boxMeta.style.display = 'none';
            return;
        }

        const formData = new FormData();
        formData.append('categoria_id', catId);
        formData.append('data', dataVal); // Envia data do campo ou hoje
        formData.append('cartao_id', cartaoId);

        fetch('ajax_check_meta.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(json => {
            console.log("Retorno Meta:", json); // OLHE O CONSOLE DO NAVEGADOR (F12)

            if (json.status === 'success') {
                const perc = json.percentual;
                const disponivel = json.disponivel;
                
                let corClass = 'bg-success';
                let textoClass = 'text-alert-success';
                let mensagem = `Resta <b>R$ ${disponivel.toLocaleString('pt-BR', {minimumFractionDigits:2})}</b>`;

                if (perc > 100) {
                    corClass = 'bg-danger';
                    textoClass = 'text-alert-danger';
                    mensagem = `⚠️ Excedeu R$ ${Math.abs(disponivel).toLocaleString('pt-BR', {minimumFractionDigits:2})}`;
                } else if (perc > 80) {
                    corClass = 'bg-warning';
                    textoClass = 'text-alert-warning';
                    mensagem = `Atenção: ${perc.toFixed(0)}% usado`;
                }

                boxMeta.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="fw-bold text-muted text-uppercase" style="font-size:0.7rem;">
                            Orçamento (${json.competencia_label})
                            <i class="bi bi-info-circle" title="Origem: ${json.debug_info.origem_meta}"></i>
                        </small>
                        <small class="${textoClass}" style="font-size:0.75rem;">${mensagem}</small>
                    </div>
                    <div class="meta-progress-bg">
                        <div class="meta-progress-fill ${corClass}" style="width: ${Math.min(perc, 100)}%"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <small class="text-muted" style="font-size:0.7rem;">Gasto: R$ ${json.gasto.toLocaleString('pt-BR', {minimumFractionDigits:2})}</small>
                        <small class="text-muted" style="font-size:0.7rem;">Meta: R$ ${json.meta.toLocaleString('pt-BR', {minimumFractionDigits:2})}</small>
                    </div>
                `;
                boxMeta.style.display = 'block';
            } else {
                // Se retornou 'no_meta', esconde
                boxMeta.style.display = 'none';
            }
        })
        .catch(err => {
            console.error(err);
            boxMeta.style.display = 'none';
        });
    }

    // --- Outras Lógicas (Mantidas) ---
    function verificarPrevisaoFatura() {
        const cartaoId = selectCartao.value;
        const dataSelecionada = inputData.value;

        if (!cartaoId) {
            labelData.innerText = "Data de Vencimento";
            feedbackFatura.style.display = 'none';
            return;
        }

        labelData.innerText = "Data da Compra";
        if (!dataSelecionada) return;

        const opcaoCartao = selectCartao.options[selectCartao.selectedIndex];
        const diaFechamento = parseInt(opcaoCartao.getAttribute('data-fechamento')) || 1;

        const dateObj = new Date(dataSelecionada + "T12:00:00");
        const diaCompra = dateObj.getDate();
        let dataFatura = new Date(dateObj);
        
        if (diaCompra >= diaFechamento) {
            dataFatura.setMonth(dataFatura.getMonth() + 1);
        }

        const nomeMes = dataFatura.toLocaleString('pt-BR', { month: 'long', year: 'numeric' });
        feedbackFatura.innerHTML = `<span class="badge-fatura"><i class="bi bi-calendar-check"></i> Fatura: ${nomeMes.charAt(0).toUpperCase() + nomeMes.slice(1)}</span>`;
        feedbackFatura.style.display = 'block';
    }

    // Listeners para atualizar
    selectCartao.addEventListener('change', () => { verificarPrevisaoFatura(); verificarMeta(); });
    
    // IMPORTANTE: Ao mudar a data, chama a verificação da meta novamente
    inputData.addEventListener('change', () => { 
        verificarPrevisaoFatura(); 
        verificarMeta(); 
    });

    document.getElementById('valor_display').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, "");
        value = (value / 100).toFixed(2);
        e.target.value = value == "0.00" ? "" : value.replace(".", ",").replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
        document.getElementById('valor_real').value = value;
    });

    document.querySelectorAll('input[name="contatipo"]').forEach(r => {
        r.addEventListener('change', () => {
            const isEntrada = (r.value === 'Entrada');
            document.getElementById('divCartao').style.display = isEntrada ? 'none' : 'block';
            if(isEntrada) { selectCartao.value = ""; verificarPrevisaoFatura(); }
            filtrarCategorias();
            verificarMeta();
        });
    });

    function filtrarCategorias() {
        if (!tomControl) return;
        const tipoSelecionado = document.querySelector('input[name="contatipo"]:checked').value;
        const valorAtual = tomControl.getValue();
        
        const categoriasJson = <?= json_encode(array_map(function($c) {
            return ['id' => $c['categoriaid'], 'text' => $c['categoriadescricao'], 'tipo' => ($c['categoriatipo'] == 'Receita') ? 'Entrada' : 'Saída'];
        }, $categorias)) ?>;

        tomControl.clearOptions();
        categoriasJson.forEach(cat => {
            if (cat.tipo === tipoSelecionado) {
                tomControl.addOption({value: cat.id, text: cat.text});
            }
        });

        const existe = categoriasJson.find(c => c.id == valorAtual && c.tipo == tipoSelecionado);
        if (existe) tomControl.setValue(valorAtual);
        else tomControl.clear();
        tomControl.refreshOptions(false);
    }

    function salvarCategoriaRapida() {
        const nome = document.getElementById('nome_nova_categoria').value;
        const tipo = document.getElementById('tipo_nova_categoria').value;
        if (!nome) return alert("Digite o nome.");

        const fd = new FormData();
        fd.append('categoriadescricao', nome);
        fd.append('categoriatipo', tipo);

        fetch('ajax_rapido_categoria.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                tomControl.addOption({value: data.id, text: data.nome, tipo: tipo});
                tomControl.setValue(data.id);
                bootstrap.Modal.getInstance(document.getElementById('modalRapidoCategoria')).hide();
                document.getElementById('nome_nova_categoria').value = "";
            } else alert(data.message);
        });
    }
</script>

<?php require_once "../includes/footer.php"; ?>