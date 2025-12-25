<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX - Sistema Integrado</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode/1.5.3/qrcode.min.js"></script>
    <style>
        .pix-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .pix-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
        .pix-button {
            background: #00d4aa;
            transition: all 0.3s ease;
        }
        .pix-button:hover {
            background: #00b894;
            transform: translateY(-2px);
        }
        .loading {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #00d4aa;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="pix-container min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center bg-white/20 backdrop-blur-sm rounded-full px-4 py-2 mb-4">
                <img src="https://logopng.com.br/logos/pix-106.png" alt="PIX" class="h-6 w-6 mr-2">
                <span class="text-white font-semibold">Pagamento Instantâneo</span>
            </div>
            <h1 class="text-2xl font-bold text-white mb-2">Pagamento Seguro</h1>
            <p class="text-white/80 text-sm">Escaneie o QR Code ou copie o código PIX</p>
        </div>

        <!-- Main Card -->
        <div class="pix-card rounded-2xl shadow-2xl overflow-hidden">
            <!-- Order Summary -->
            <div class="bg-gray-50 p-6 border-b">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">Resumo do Pedido</h2>
                    <div class="bg-green-100 text-green-800 text-xs font-bold px-2 py-1 rounded-full">
                        🛡️ Seguro
                    </div>
                </div>
                
                <div class="space-y-3">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center">
                            <span class="text-gray-500 text-xl">📦</span>
                        </div>
                        <div class="flex-1">
                            <p class="font-medium text-gray-800" id="product-name">Produto/Serviço</p>
                            <p class="text-sm text-gray-500" id="product-description">Descrição do produto</p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-gray-800" id="product-quantity">1x</p>
                        </div>
                    </div>
                    
                    <div class="border-t pt-3">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="text-gray-800" id="subtotal">R$ 0,00</span>
                        </div>
                        <div class="flex justify-between items-center text-green-600">
                            <span>Desconto PIX</span>
                            <span id="discount">-R$ 0,00</span>
                        </div>
                        <div class="flex justify-between items-center mt-2 pt-2 border-t">
                            <span class="text-lg font-bold text-gray-800">Total</span>
                            <span class="text-lg font-bold text-green-600" id="total-amount">R$ 0,00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Section -->
            <div class="p-6">
                <div class="text-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">QR Code PIX</h3>
                    <p class="text-sm text-gray-600">Escaneie com seu aplicativo bancário</p>
                </div>

                <!-- QR Code Container -->
                <div class="flex justify-center mb-6">
                    <div class="bg-white p-4 rounded-xl border-2 border-dashed border-gray-200">
                        <div id="qrcode-container" class="w-48 h-48 flex items-center justify-center">
                            <div class="loading"></div>
                        </div>
                    </div>
                </div>

                <!-- PIX Code -->
                <div class="bg-gray-50 rounded-xl p-4 mb-6">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-600">Código PIX:</span>
                        <button id="copy-btn" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            Copiar
                        </button>
                    </div>
                    <div class="bg-white p-3 rounded border text-xs text-gray-700 break-all font-mono" id="pix-code">
                        Carregando código PIX...
                    </div>
                </div>

                <!-- Instructions -->
                <div class="bg-blue-50 rounded-xl p-4 mb-6">
                    <h4 class="font-medium text-blue-800 mb-2">Como pagar:</h4>
                    <ol class="text-sm text-blue-700 space-y-1 list-decimal list-inside">
                        <li>Abra seu aplicativo bancário</li>
                        <li>Escaneie o QR Code ou cole o código PIX</li>
                        <li>Confirme o pagamento</li>
                        <li>Aguarde a confirmação (instantânea)</li>
                    </ol>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-3">
                    <button id="generate-pix-btn" class="w-full pix-button text-white font-bold py-4 rounded-xl text-lg">
                        Gerar PIX
                    </button>
                    <button id="back-btn" class="w-full bg-gray-200 text-gray-800 font-bold py-3 rounded-xl hover:bg-gray-300 transition-colors">
                        Voltar
                    </button>
                </div>

                <!-- Status Messages -->
                <div id="status-messages" class="mt-4 space-y-2"></div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6">
            <div class="inline-flex items-center bg-white/20 backdrop-blur-sm rounded-full px-4 py-2">
                <div class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center mr-2">
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <span class="text-white text-sm font-medium">Ambiente Seguro</span>
            </div>
            <p class="text-white/60 text-xs mt-2">© 2025 Sistema de Pagamentos</p>
        </div>
    </div>

    <script>
        class PixCheckout {
            constructor() {
                this.amount = 68.92; // Valor padrão
                this.pixCode = '';
                this.transactionId = null;
                this.init();
            }

            init() {
                this.bindEvents();
                this.loadOrderData();
                this.generatePix();
            }

            bindEvents() {
                document.getElementById('copy-btn').addEventListener('click', () => this.copyPixCode());
                document.getElementById('generate-pix-btn').addEventListener('click', () => this.generatePix());
                document.getElementById('back-btn').addEventListener('click', () => this.goBack());
            }

            loadOrderData() {
                // Carregar dados do pedido (pode vir de URL params, localStorage, etc)
                const urlParams = new URLSearchParams(window.location.search);
                
                if (urlParams.has('amount')) {
                    this.amount = parseFloat(urlParams.get('amount'));
                }

                // Atualizar UI
                document.getElementById('total-amount').textContent = this.formatMoney(this.amount);
                document.getElementById('subtotal').textContent = this.formatMoney(this.amount);
                
                // Simular desconto de 5% para PIX
                const discount = this.amount * 0.05;
                document.getElementById('discount').textContent = '-' + this.formatMoney(discount);
            }

            async generatePix() {
                this.showLoading();
                
                try {
                    // Dados do cliente (em produção, virão do sistema)
                    const customerData = {
                        name: 'Cliente Teste',
                        document: '12345678909',
                        email: 'cliente@teste.com',
                        phone: '11999999999',
                        address: {
                            street: 'Rua Teste',
                            number: '123',
                            neighborhood: 'Centro',
                            city: 'São Paulo',
                            state: 'SP',
                            zipCode: '01234000'
                        }
                    };

                    const products = [
                        {
                            description: 'Pagamento seguro',
                            quantity: 1,
                            value: this.amount
                        }
                    ];

                    // Chamar API para gerar PIX
                    const response = await fetch('/api/gateways/versellpay/pix/generate', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + this.getAuthToken()
                        },
                        body: JSON.stringify({
                            requestNumber: 'PIX_' + Date.now(),
                            amount: this.amount,
                            client: customerData,
                            products: products,
                            callbackUrl: window.location.origin + '/webhooks/versellpay'
                        })
                    });

                    const data = await response.json();

                    if (data.status) {
                        this.pixCode = data.paymentCode;
                        this.transactionId = data.idTransaction;
                        this.displayPixCode();
                        this.startStatusCheck();
                        this.showSuccess('PIX gerado com sucesso!');
                    } else {
                        this.showError(data.msg || 'Erro ao gerar PIX');
                    }

                } catch (error) {
                    console.error('Erro ao gerar PIX:', error);
                    this.showError('Erro de conexão. Tente novamente.');
                } finally {
                    this.hideLoading();
                }
            }

            displayPixCode() {
                // Gerar QR Code
                const qrcodeContainer = document.getElementById('qrcode-container');
                qrcodeContainer.innerHTML = '';
                
                QRCode.toCanvas(this.pixCode, (error, canvas) => {
                    if (error) {
                        console.error('Erro ao gerar QR Code:', error);
                        qrcodeContainer.innerHTML = '<div class="text-red-500">Erro ao gerar QR Code</div>';
                        return;
                    }
                    
                    canvas.className = 'w-48 h-48';
                    qrcodeContainer.appendChild(canvas);
                });

                // Exibir código PIX
                document.getElementById('pix-code').textContent = this.pixCode;
            }

            copyPixCode() {
                navigator.clipboard.writeText(this.pixCode).then(() => {
                    const btn = document.getElementById('copy-btn');
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Copiado!';
                    btn.classList.add('text-green-600');
                    
                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.classList.remove('text-green-600');
                    }, 2000);
                });
            }

            startStatusCheck() {
                // Verificar status da transação a cada 5 segundos
                const checkInterval = setInterval(async () => {
                    if (!this.transactionId) return;

                    try {
                        const response = await fetch('/api/gateways/versellpay/transaction/consult', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Authorization': 'Bearer ' + this.getAuthToken()
                            },
                            body: JSON.stringify({
                                idTransaction: this.transactionId
                            })
                        });

                        const data = await response.json();

                        if (data.status && data.transaction) {
                            const transaction = data.transaction;
                            
                            if (transaction.processingStatus === 'PAID_OUT') {
                                clearInterval(checkInterval);
                                this.showSuccess('Pagamento confirmado com sucesso!');
                                setTimeout(() => {
                                    this.redirectToSuccess();
                                }, 2000);
                            } else if (transaction.processingStatus === 'NOT_PAID') {
                                clearInterval(checkInterval);
                                this.showError('Pagamento não realizado.');
                            }
                        }
                    } catch (error) {
                        console.error('Erro ao verificar status:', error);
                    }
                }, 5000);

                // Parar verificação após 30 minutos
                setTimeout(() => {
                    clearInterval(checkInterval);
                }, 30 * 60 * 1000);
            }

            getAuthToken() {
                // Em produção, obter token de autenticação do sistema
                return localStorage.getItem('auth_token') || '';
            }

            formatMoney(value) {
                return new Intl.NumberFormat('pt-BR', {
                    style: 'currency',
                    currency: 'BRL'
                }).format(value);
            }

            showLoading() {
                document.getElementById('generate-pix-btn').innerHTML = '<div class="loading mx-auto"></div>';
                document.getElementById('generate-pix-btn').disabled = true;
            }

            hideLoading() {
                document.getElementById('generate-pix-btn').innerHTML = 'Gerar PIX';
                document.getElementById('generate-pix-btn').disabled = false;
            }

            showSuccess(message) {
                this.showMessage(message, 'success');
            }

            showError(message) {
                this.showMessage(message, 'error');
            }

            showMessage(message, type) {
                const container = document.getElementById('status-messages');
                const alertClass = type === 'success' ? 'bg-green-100 text-green-800 border-green-200' : 'bg-red-100 text-red-800 border-red-200';
                
                const messageDiv = document.createElement('div');
                messageDiv.className = `p-3 rounded-lg border ${alertClass}`;
                messageDiv.textContent = message;
                
                container.appendChild(messageDiv);
                
                setTimeout(() => {
                    messageDiv.remove();
                }, 5000);
            }

            redirectToSuccess() {
                // Redirecionar para página de sucesso
                const urlParams = new URLSearchParams(window.location.search);
                const redirectUrl = urlParams.get('redirect') || '/payment/success';
                window.location.href = redirectUrl + '?transaction=' + this.transactionId;
            }

            goBack() {
                window.history.back();
            }
        }

        // Inicializar checkout quando a página carregar
        document.addEventListener('DOMContentLoaded', () => {
            new PixCheckout();
        });
    </script>
</body>
</html>
