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
    .amount-container { padding: 30px 0; text-align: center; }
    #valor_display { font-size: 3.5rem; font-weight: 800; border: none; background: transparent; outline: none; width: 100%; text-align: center; color: var(--app-text-main); }
    .amount-label { font-weight: 600; color: #94a3b8; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }

    /* Tipo */
    .type-picker { background: #e2e8f0; padding: 4px; border-radius: 16px; display: flex; gap: 4px; margin-bottom: 25px; max-width: 400px; margin: 0 auto 25px auto; }
    .type-picker .btn-check + .btn { flex: 1; border: none; border-radius: 12px; padding: 12px; font-weight: 700; color: #64748b; background: transparent; }
    .type-picker .btn-check:checked + .btn { background: #fff; color: var(--app-text-main); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }

    /* Cards e Inputs */
    .card-app { background: var(--app-card); border-radius: 24px; padding: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.05); height: 100%; }
    .label-app { font-size: 0.8rem; font-weight: 700; color: #64748b; margin-bottom: 8px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
    .input-app { background-color: #f1f5f9 !important; border: 2px solid transparent !important; padding: 12px 16px !important; border-radius: 14px !important; font-size: 1rem; font-weight: 600; color: #334155; height: 54px; }
    .input-app:focus { background: #fff !important; border-color: #4361ee !important; box-shadow: 0 0 0 4px rgba(67,97,238,0.1); }

    /* Tom Select */
    .ts-wrapper .ts-control { background-color: #f1f5f9 !important; border: 2px solid transparent !important; border-radius: 14px !important; padding: 12px 16px !important; font-size: 1rem !important; font-weight: 600 !important; min-height: 54px; display: flex; align-items: center; }
    .ts-dropdown { border-radius: 16px !important; border: none !important; box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important; padding: 8px; z-index: 9999; }
    .ts-dropdown .option { padding: 12px 16px !important; border-radius: 8px; font-size: 1rem; }
    .ts-dropdown .active { background-color: #e2e8f0 !important; color: #000 !important; }

    /* Switch */
    .switch-container { 
        background: #f8fafc; border: 2px solid #e2e8f0; 
        padding: 16px; border-radius: 16px; 
        display: flex; justify-content: space-between; align-items: center; 
        margin-bottom: 20px; cursor: pointer; transition: 0.2s; 
    }
    .switch-container:active { background-color: #f1f5f9; transform: scale(0.98); }
    .switch-container.active { border-color: #4361ee; background-color: #eff6ff; }
    .form-check-input { width: 3em; height: 1.5em; cursor: pointer; }

    /* Ações */
    .action-buttons { display: flex; gap: 15px; margin-top: 20px; }
    .btn-confirm { background: #1e293b; color: #fff; border-radius: 16px; padding: 16px; font-weight: 700; border: none; flex: 2; transition: 0.2s; }
    .btn-add-more { background: #fff; color: #1e293b; border: 2px solid #e2e8f0; border-radius: 16px; padding: 16px; font-weight: 700; flex: 1; transition: 0.2s; }
    
    .badge-fatura { background-color: #dbeafe; color: #1e40af; padding: 6px 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; margin-top: 8px; display: inline-block; }

    #feedbackMeta { display: none; margin-top: 15px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; }
    .meta-progress-bg { height: 6px; background: #f1f5f9; border-radius: 4px; overflow: hidden; margin: 8px 0; }
    .meta-progress-fill { height: 100%; transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1); }
    .text-alert-danger { color: #ef4444; font-weight: 800; }
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
                        <input type="text" name="contadescricao" class="form-control input-app" placeholder="Ex: Mercado, Uber..." required>
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
                        <select name="cartoid" id="selectCartao" placeholder="Buscar cartão..." autocomplete="off">
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
                    <div class="switch-container" id="boxFixa" onclick="toggleFixa()">
                        <div>
                            <span class="fw-bold d-block text-dark">Despesa Fixa?</span>
                            <small class="text-muted" style="font-size: 0.75rem;">Repete todo mês</small>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="contafixa" value="1" id="checkFixa" <?= ($fixa_pre == 1) ? 'checked' : '' ?> onclick="event.stopPropagation(); toggleFixa(true);">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="label-app" id="labelData">Data</label>
                        <input type="date" name="contavencimento" id="contavencimento" class="form-control input-app" value="<?= $venc_pre ?>" required>
                        <div id="feedbackFatura" class="fade-in mt-2" style="display:none;"></div>
                    </div>

                    <div id="containerParcelas">
                        <label class="label-app">Qtd. Parcelas</label>
                        <input type="number" name="contaparcela_total" id="inputParcelas" class="form-control input-app text-center" value="1" min="1">
                    </div>
                </div>
            </div>
        </div>

        <div class="action-buttons pb-5">
            <button type="submit" onclick="document.getElementById('manter_dados').value='1'" class="btn-add-more">Salvar +</button>
            <button type="submit" onclick="document.getElementById('manter_dados').value='0'" class="btn-confirm">Confirmar</button>
        </div>
    </form>
</div>

<div class="modal fade" id="modalRapidoCategoria" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 350px;">
        <div class="modal-content border-0 shadow" style="border-radius: 24px;">
            <div class="modal-body p-4">
                <h6 class="fw-bold mb-3">Nova Categoria</h6>
                <div class="mb-3">
                    <label class="label-app">Nome</label>
                    <input type="text" id="nome_nova_categoria" class="form-control input-app">
                </div>
                <div class="mb-3">
                    <label class="label-app">Tipo</label>
                    <select id="tipo_nova_categoria" class="form-select input-app">
                        <option value="Saída">Despesa</option>
                        <option value="Entrada">Receita</option>
                    </select>
                </div>
                <button type="button" onclick="salvarCategoriaRapida()" class="btn-confirm w-100 py-3">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

<script>
    let tomCategoria, tomCartao;
    const inputData = document.getElementById('contavencimento');
    const labelData = document.getElementById('labelData');
    const feedbackFatura = document.getElementById('feedbackFatura');
    const boxMeta = document.getElementById('feedbackMeta');
    const boxFixa = document.getElementById('boxFixa');
    const checkFixa = document.getElementById('checkFixa');
    const divParcelas = document.getElementById('containerParcelas');

    window.addEventListener('DOMContentLoaded', () => {
        // TomSelect Categoria
        if (document.getElementById('selectCategoria')) {
            tomCategoria = new TomSelect('#selectCategoria', {
                create: false, sortField: { field: "text", direction: "asc" }, allowEmptyOption: true,
                onChange: () => verificarMeta()
            });
            filtrarCategorias();
        }

        // TomSelect Cartão
        if (document.getElementById('selectCartao')) {
            tomCartao = new TomSelect('#selectCartao', {
                create: false, sortField: { field: "text", direction: "asc" }, allowEmptyOption: true,
                onChange: () => { 
                    verificarPrevisaoFatura(); 
                    verificarMeta(); 
                }
            });
        }
        
        verificarPrevisaoFatura();
        atualizarParcelasVisibilidade();
    });

    // Lógica UX Fixa
    function toggleFixa(fromCheckbox = false) {
        if (!fromCheckbox) checkFixa.checked = !checkFixa.checked;
        if(checkFixa.checked) boxFixa.classList.add('active');
        else boxFixa.classList.remove('active');
        atualizarParcelasVisibilidade();
    }

    function atualizarParcelasVisibilidade() {
        if(checkFixa.checked) {
            divParcelas.style.display = 'none';
            document.getElementById('inputParcelas').value = 1;
        } else {
            divParcelas.style.display = 'block';
        }
    }

    // --- CORREÇÃO AQUI: Lógica de Exibição da Fatura ---
    function verificarPrevisaoFatura() {
        const cartaoId = tomCartao ? tomCartao.getValue() : '';
        const dataSelecionada = inputData.value;

        if (!cartaoId) {
            labelData.innerText = "Data de Vencimento";
            feedbackFatura.style.display = 'none';
            return;
        }

        labelData.innerText = "Data da Compra";
        if (!dataSelecionada) return;

        const selectOriginal = document.getElementById('selectCartao');
        const optionSelecionada = selectOriginal.querySelector(`option[value="${cartaoId}"]`);
        const diaFechamento = optionSelecionada ? (parseInt(optionSelecionada.getAttribute('data-fechamento')) || 1) : 1;

        const dateObj = new Date(dataSelecionada + "T12:00:00");
        const diaCompra = dateObj.getDate();
        
        // Clona a data e seta para o dia 1 para evitar bugs de virada de mês (ex: 31 jan -> fev)
        let dataFatura = new Date(dateObj);
        dataFatura.setDate(1); 

        // LÓGICA DE CORTE:
        if (diaCompra >= diaFechamento) {
            // Se fechou dia 29 e comprei dia 29 -> Pula o mês atual E o próximo
            // Ex: Compra 29 Dez -> Pula Jan -> Vence Fev
            dataFatura.setMonth(dataFatura.getMonth() + 2);
        } else {
            // Se fechou dia 29 e comprei dia 28 -> Pula só o mês atual
            // Ex: Compra 28 Dez -> Vence Jan
            dataFatura.setMonth(dataFatura.getMonth() + 1);
        }

        const nomeMes = dataFatura.toLocaleString('pt-BR', { month: 'long', year: 'numeric' });
        feedbackFatura.innerHTML = `<span class="badge-fatura"><i class="bi bi-calendar-check"></i> Fatura Vence em: ${nomeMes.charAt(0).toUpperCase() + nomeMes.slice(1)}</span>`;
        feedbackFatura.style.display = 'block';
    }

    // ... (Resto do código mantido igual: verificarMeta, listeners, etc) ...
    function verificarMeta() {
        const catId = tomCategoria ? tomCategoria.getValue() : '';
        const cartaoId = tomCartao ? tomCartao.getValue() : '';
        let dataVal = inputData.value;
        if (!dataVal) dataVal = new Date().toISOString().split('T')[0];
        const tipoConta = document.querySelector('input[name="contatipo"]:checked').value;

        if (tipoConta === 'Entrada' || !catId) { boxMeta.style.display = 'none'; return; }

        const formData = new FormData();
        formData.append('categoria_id', catId);
        formData.append('data', dataVal);
        formData.append('cartao_id', cartaoId);

        fetch('ajax_check_meta.php', { method: 'POST', body: formData })
        .then(r => r.json()).then(json => {
            if (json.status === 'success') {
                const perc = json.percentual;
                const disponivel = json.disponivel;
                let corClass = perc > 100 ? 'bg-danger' : (perc > 80 ? 'bg-warning' : 'bg-success');
                let textoClass = perc > 100 ? 'text-alert-danger' : 'text-alert-success';
                let msg = perc > 100 ? `Excedeu` : `Resta`;

                boxMeta.innerHTML = `
                    <div class="d-flex justify-content-between mb-1">
                        <small class="fw-bold text-muted" style="font-size:0.7rem;">Orçamento (${json.competencia_label})</small>
                        <small class="${textoClass}" style="font-size:0.75rem;">${msg} <b>R$ ${Math.abs(disponivel).toLocaleString('pt-BR',{minimumFractionDigits:2})}</b></small>
                    </div>
                    <div class="meta-progress-bg"><div class="meta-progress-fill ${corClass}" style="width: ${Math.min(perc, 100)}%"></div></div>
                `;
                boxMeta.style.display = 'block';
            } else { boxMeta.style.display = 'none'; }
        }).catch(() => boxMeta.style.display = 'none');
    }

    inputData.addEventListener('change', () => { verificarPrevisaoFatura(); verificarMeta(); });

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
            if(isEntrada && tomCartao) { tomCartao.clear(); verificarPrevisaoFatura(); }
            filtrarCategorias();
            verificarMeta();
        });
    });

    function filtrarCategorias() {
        if (!tomCategoria) return;
        const tipoSelecionado = document.querySelector('input[name="contatipo"]:checked').value;
        const valorAtual = tomCategoria.getValue();
        const categoriasJson = <?= json_encode(array_map(function($c) {
            return ['id' => $c['categoriaid'], 'text' => $c['categoriadescricao'], 'tipo' => ($c['categoriatipo'] == 'Receita') ? 'Entrada' : 'Saída'];
        }, $categorias)) ?>;
        tomCategoria.clearOptions();
        categoriasJson.forEach(cat => {
            if (cat.tipo === tipoSelecionado) tomCategoria.addOption({value: cat.id, text: cat.text});
        });
        const existe = categoriasJson.find(c => c.id == valorAtual && c.tipo == tipoSelecionado);
        if (existe) tomCategoria.setValue(valorAtual); else tomCategoria.clear();
        tomCategoria.refreshOptions(false);
    }

    function salvarCategoriaRapida() {
        const nome = document.getElementById('nome_nova_categoria').value;
        const tipo = document.getElementById('tipo_nova_categoria').value;
        if (!nome) return alert("Digite o nome.");
        const fd = new FormData();
        fd.append('categoriadescricao', nome); fd.append('categoriatipo', tipo);
        fetch('ajax_rapido_categoria.php', { method: 'POST', body: fd }).then(res => res.json()).then(data => {
            if (data.status === 'success') {
                tomCategoria.addOption({value: data.id, text: data.nome});
                tomCategoria.setValue(data.id);
                bootstrap.Modal.getInstance(document.getElementById('modalRapidoCategoria')).hide();
                document.getElementById('nome_nova_categoria').value = "";
            } else alert(data.message);
        });
    }
</script>

<?php require_once "../includes/footer.php"; ?>