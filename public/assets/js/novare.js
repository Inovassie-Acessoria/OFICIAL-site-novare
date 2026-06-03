/* Novare Brindes — interações (vanilla, sem dependências) */
(function () {
    'use strict';

    /* ---------- Menu mobile ---------- */
    const menuBtn = document.querySelector('[data-menu]');
    if (menuBtn) {
        const links = document.querySelector('.nav-links');
        menuBtn.addEventListener('click', function () {
            const open = links.classList.toggle('open');
            menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    /* ---------- Selects/inputs que enviam o form ao mudar ---------- */
    document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
        el.addEventListener('change', function () {
            if (el.form) el.form.submit();
        });
    });

    /* ---------- Página de produto: swatches + galeria + CTA ---------- */
    const prod = document.querySelector('[data-produto]');
    if (prod) {
        const dataEl = document.getElementById('variacoes-data');
        let variacoes = [];
        try { variacoes = JSON.parse(dataEl.textContent || '[]'); } catch (e) { variacoes = []; }

        const nome = prod.dataset.nome || '';
        const urlProduto = prod.dataset.url || '';
        const whats = prod.dataset.whats || '';
        const imgMain = document.getElementById('gallery-img');
        const thumbs = document.getElementById('gallery-thumbs');
        const corAtiva = document.getElementById('cor-ativa');
        const skuAtivo = document.getElementById('sku-ativo');
        const cta = document.getElementById('cta-whats');
        const swatches = Array.from(document.querySelectorAll('#color-swatches button'));

        function montarMsg(img) {
            var msg = 'Olá, tudo bem? Gostaria de fazer um orçamento de ' + nome;
            if (img) msg += ' (' + img + ')';
            return msg + ', poderia me ajudar?';
        }

        // Definição dos 5 ângulos de fallback com seus estilos CSS e tooltips descritivos em português (Aprovado em /grill-me)
        const angulosFallback = [
            {
                nome: 'Principal',
                style: 'transform: none; transform-origin: center;',
                thumbStyle: 'transform: scale(0.95);'
            },
            {
                nome: 'Perspectiva 3D',
                style: 'transform: scale(1.1) rotate(2.5deg) skewY(1deg); filter: drop-shadow(0 6px 12px rgba(0,0,0,0.08));',
                thumbStyle: 'transform: scale(0.9) rotate(2.5deg) skewY(1deg);'
            },
            {
                nome: 'Detalhe (Logo)',
                style: 'transform: scale(1.55); transform-origin: center;',
                thumbStyle: 'transform: scale(1.35); transform-origin: center;'
            },
            {
                nome: 'Verso Espelhado',
                style: 'transform: scaleX(-1);',
                thumbStyle: 'transform: scaleX(-0.95);'
            },
            {
                nome: 'Foco Superior',
                style: 'transform: scale(1.75); transform-origin: top;',
                thumbStyle: 'transform: scale(1.4); transform-origin: top;'
            }
        ];

        function selecionar(idx) {
            const v = variacoes[idx];
            if (!v) return;

            // Galeria Inteligente de 5 Ângulos Dinâmicos (Aprovado em /grill-me)
            let imgs = (v.imagens && v.imagens.length) ? v.imagens : (imgMain ? [imgMain.src] : []);
            
            // Força reset de estilo do main image antes de carregar nova variação
            if (imgMain) {
                imgMain.style.cssText = 'transition: all 0.4s ease-out; transform: none; transform-origin: center;';
            }

            if (thumbs) {
                thumbs.innerHTML = '';

                // Se houver apenas 1 imagem cadastrada, geramos os 5 ângulos de fallback via CSS
                if (imgs.length === 1 && imgs[0] !== '') {
                    const src = imgs[0];
                    if (imgMain) imgMain.src = src;

                    angulosFallback.forEach(function (ang, i) {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.className = i === 0 ? 'active relative overflow-hidden group/thumb' : 'relative overflow-hidden group/thumb';
                        b.title = ang.nome;
                        
                        // Cria thumbnail com o efeito reduzido aplicado na imagem
                        b.innerHTML = '<div class="w-full h-full flex items-center justify-center overflow-hidden rounded">' +
                            '<img src="' + src + '" alt="' + ang.nome + '" style="' + ang.thumbStyle + ' transition: all 0.2s;" class="object-contain w-full h-full">' +
                            '</div>' +
                            '<span class="absolute bottom-0 inset-x-0 bg-slate-900/80 text-white text-[8px] font-bold uppercase py-0.5 text-center translate-y-full group-hover/thumb:translate-y-0 transition-transform duration-200 pointer-events-none">' + ang.nome + '</span>';
                        
                        b.addEventListener('click', function () {
                            if (imgMain) {
                                // Efeito de opacidade suave no clique
                                imgMain.style.opacity = '0';
                                setTimeout(function () {
                                    imgMain.src = src;
                                    imgMain.style.cssText = 'transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94); ' + ang.style;
                                    imgMain.style.opacity = '1';
                                }, 150);
                            }
                            thumbs.querySelectorAll('button').forEach(function (x) { x.classList.remove('active'); });
                            b.classList.add('active');
                        });
                        thumbs.appendChild(b);
                    });
                } else {
                    // Caso contrário, usamos as imagens disponíveis de forma padrão
                    if (imgMain && imgs[0]) imgMain.src = imgs[0];
                    imgs.forEach(function (src, i) {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.className = i === 0 ? 'active' : '';
                        b.innerHTML = '<img src="' + src + '" alt="Ângulo ' + (i+1) + '" loading="lazy" class="object-contain">';
                        b.addEventListener('click', function () {
                            if (imgMain) {
                                imgMain.style.opacity = '0';
                                setTimeout(function () {
                                    imgMain.src = src;
                                    imgMain.style.cssText = 'transition: all 0.4s ease-out; transform: none;';
                                    imgMain.style.opacity = '1';
                                }, 150);
                            }
                            thumbs.querySelectorAll('button').forEach(function (x) { x.classList.remove('active'); });
                            b.classList.add('active');
                        });
                        thumbs.appendChild(b);
                    });
                }
            }

            // textos + sku
            if (corAtiva) corAtiva.textContent = v.cor || '';
            if (skuAtivo) skuAtivo.textContent = v.sku || '';

            // CTA WhatsApp da variação ativa (usa a imagem da cor selecionada)
            if (cta && whats) {
                var imgVar = (v.imagens && v.imagens[0]) || (imgMain ? imgMain.src : '');
                cta.href = 'https://wa.me/' + whats + '?text=' + encodeURIComponent(montarMsg(imgVar));
            }

            swatches.forEach(function (s) { s.classList.remove('active'); });
            if (swatches[idx]) swatches[idx].classList.add('active');
        }

        swatches.forEach(function (s) {
            s.addEventListener('click', function () { selecionar(parseInt(s.dataset.index, 10) || 0); });
        });
        if (variacoes.length) selecionar(0);
    }

    /* ---------- Widget de chat (assistente IA Sophia com upload/Ctrl+V) ---------- */
    const fabs = document.querySelectorAll('[data-chat-open]');
    const panel = document.getElementById('chat-panel');
    if (fabs.length && panel) {
        const body = document.getElementById('chat-body');
        const form = document.getElementById('chat-form');
        const input = document.getElementById('chat-input');
        const balloon = document.getElementById('chat-balloon');
        
        // Elementos de Upload Multimodal
        const attachBtn = document.getElementById('chat-attach-btn');
        const fileInput = document.getElementById('chat-file');
        const previewContainer = document.getElementById('chat-preview-container');
        const previewImg = document.getElementById('chat-preview-img');
        const previewName = document.getElementById('chat-preview-name');
        const previewCancel = document.getElementById('chat-preview-cancel');

        const historico = [];
        let carregando = false;
        let imagemBase64 = null; // Guarda a imagem anexada/colada

        // Animação de fade-in tardia do balão lateral (1.5 segundos após load)
        if (balloon) {
            setTimeout(function () {
                balloon.classList.remove('opacity-0', 'translate-x-[10px]', 'pointer-events-none');
                balloon.classList.add('opacity-100', 'translate-x-0', 'pointer-events-auto');
                
                // Ativa pulso sutil de tempos em tempos
                setInterval(function () {
                    if (!panel.classList.contains('open')) {
                        balloon.style.transform = 'translateY(-4px)';
                        setTimeout(function () {
                            balloon.style.transform = 'translateY(0)';
                        }, 500);
                    }
                }, 30000);
            }, 1500);
        }

        // Abre o chat ao clicar nos gatilhos
        fabs.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const open = panel.classList.toggle('open');
                panel.setAttribute('aria-hidden', open ? 'false' : 'true');
                if (open) {
                    input.focus();
                    if (balloon) {
                        balloon.classList.add('opacity-0', 'pointer-events-none');
                    }
                }
            });
        });

        const closeBtn = document.getElementById('chat-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                panel.classList.remove('open');
                panel.setAttribute('aria-hidden', 'true');
                if (balloon) {
                    balloon.classList.remove('opacity-0', 'pointer-events-none');
                }
            });
        }

        // Lógica de pré-visualização de arquivos de imagem
        function processarArquivoImagem(file) {
            if (!file || !file.type.startsWith('image/')) return;

            const reader = new FileReader();
            reader.onload = function (e) {
                imagemBase64 = e.target.result;
                if (previewImg && previewName && previewContainer) {
                    previewImg.src = imagemBase64;
                    previewName.textContent = file.name || 'print_clipboard.png';
                    previewContainer.classList.remove('hidden');
                    body.scrollTop = body.scrollHeight;
                }
            };
            reader.readAsDataURL(file);
        }

        // Trigger manual de seleção de arquivo
        if (attachBtn && fileInput) {
            attachBtn.addEventListener('click', function () {
                fileInput.click();
            });
            fileInput.addEventListener('change', function () {
                if (fileInput.files && fileInput.files[0]) {
                    processarArquivoImagem(fileInput.files[0]);
                }
            });
        }

        // Captura de eventos 'paste' (Ctrl+V) no input (Multimodalidade)
        if (input) {
            input.addEventListener('paste', function (e) {
                const items = (e.clipboardData || window.clipboardData).items;
                for (let i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('image') !== -1) {
                        const file = items[i].getAsFile();
                        processarArquivoImagem(file);
                        e.preventDefault(); // Evita colar texto com metadados do clipboard
                        break;
                    }
                }
            });

            // Drag and drop de imagens no painel
            panel.addEventListener('dragover', function (e) {
                e.preventDefault();
            });
            panel.addEventListener('drop', function (e) {
                e.preventDefault();
                if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                    processarArquivoImagem(e.dataTransfer.files[0]);
                }
            });
        }

        // Cancelar imagem anexada
        if (previewCancel) {
            previewCancel.addEventListener('click', function () {
                cancelarAnexo();
            });
        }

        function cancelarAnexo() {
            imagemBase64 = null;
            if (fileInput) fileInput.value = '';
            if (previewContainer) {
                previewContainer.classList.add('hidden');
                if (previewImg) previewImg.src = '';
            }
        }

        function addMsg(texto, classe, imgBase64 = null) {
            const div = document.createElement('div');
            div.className = 'chat-msg ' + classe;
            
            if (texto) {
                const p = document.createElement('span');
                p.textContent = texto;
                div.appendChild(p);
            }

            // Exibe a imagem de forma integrada na bolha do chat do usuário
            if (imgBase64) {
                const img = document.createElement('img');
                img.src = imgBase64;
                img.alt = 'Imagem enviada';
                img.className = 'rounded-lg max-w-full max-h-32 object-contain mt-1.5 border border-white/20 block';
                div.appendChild(img);
            }

            body.appendChild(div);
            body.scrollTop = body.scrollHeight;
            return div;
        }

        function addTyping() {
            const div = document.createElement('div');
            div.className = 'chat-msg bot';
            div.innerHTML = '<div class="chat-typing"><span></span><span></span><span></span></div>';
            body.appendChild(div);
            body.scrollTop = body.scrollHeight;
            return div;
        }

        function addProdutos(produtos) {
            if (!produtos || !produtos.length) return;
            const wrap = document.createElement('div');
            wrap.className = 'chat-msg bot chat-suggestions';
            
            // Limitamos a sugestão a exatamente 3 produtos quando for uma busca por imagem
            const limite = imagemBase64 ? 3 : 6;
            const prodsExibidos = produtos.slice(0, limite);

            prodsExibidos.forEach(function (p) {
                const a = document.createElement('a');
                a.className = 'chat-prod';
                a.href = p.url;
                a.innerHTML =
                    (p.imagem ? '<img src="' + p.imagem + '" alt="" class="object-contain">' : '') +
                    '<div class="info"><strong>' + (p.nome || '') + '</strong><span>' + (p.preco || '') + '</span></div>';
                wrap.appendChild(a);
            });
            body.appendChild(wrap);
            body.scrollTop = body.scrollHeight;
        }

        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            const texto = (input.value || '').trim();
            
            // Bloqueia submit vazio se não houver imagem
            if ((!texto && !imagemBase64) || carregando) return;

            // Adiciona mensagem do usuário com ou sem imagem integrada
            addMsg(texto, 'user', imagemBase64);
            
            historico.push({ role: 'user', texto: texto });
            
            input.value = '';
            carregando = true;
            
            const typing = addTyping();
            
            // Salva a imagem a ser enviada e limpa a prévia
            const imgDataParaEnviar = imagemBase64;
            cancelarAnexo();

            fetch('/api/agent.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    mensagens: historico,
                    imagem: imgDataParaEnviar
                })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    typing.remove();
                    const resposta = data.resposta || 'Desculpe, não consegui processar agora. Tente novamente.';
                    addMsg(resposta, 'bot');
                    historico.push({ role: 'assistant', texto: resposta });
                    addProdutos(data.produtos);
                })
                .catch(function () {
                    typing.remove();
                    addMsg('Tivemos um problema de conexão. Tente novamente em instantes.', 'bot');
                })
                .finally(function () { carregando = false; });
        });
    }

    /* ---------- Formulário de Newsletter (Envio Assíncrono com Feedback Verde) ---------- */
    const newsForm = document.getElementById('newsletter-form');
    const newsSuccess = document.getElementById('newsletter-success');
    if (newsForm && newsSuccess) {
        newsForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = newsForm.querySelector('button[type="submit"]');
            btn.innerHTML = `<span class="inline-block animate-spin mr-1">&#8635;</span> PROCESSANDO...`;
            btn.disabled = true;
            
            // Simula o AJAX de cadastro com tempo de resposta refinado de 1 segundo
            setTimeout(() => {
                newsForm.style.display = 'none';
                newsSuccess.style.display = 'flex';
            }, 1000);
        });
    }
})();

