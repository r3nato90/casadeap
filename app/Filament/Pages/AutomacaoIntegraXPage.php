<?php

namespace App\Filament\Pages;

use App\Models\AutomacaoIntegraX;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Api\AutomacaoIntegraXController;

class AutomacaoIntegraXPage extends Page
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static ?string $slug = 'automacao-integrax';

    protected static string $view = 'filament.pages.automacao-integrax-page';

    public ?array $data = [];
    public ?AutomacaoIntegraX $setting = null;

    /**
     * @dev  
     * @return bool
     */
    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin');
    }
    
    public static function canView(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    /**
     * @return void
     */
    public function mount(): void
    {
        $setting = AutomacaoIntegraX::first();
        if(!empty($setting)) {
            $this->setting = $setting;
            $this->form->fill($this->setting->toArray());
        }else{
            $this->form->fill();
        }
    }

    /**
     * @param Form $form
     * @return Form
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Seção de Configuração (PRIMEIRA)
                Section::make('⚙️ CONFIGURAÇÃO DA AUTOMAÇÃO')
                    ->description('Configure sua integração com a IntegraX:')
                    ->schema([
                        TextInput::make('integrax_url')
                            ->label('🔗 URL DE INTEGRAÇÃO')
                            ->placeholder('https://sms.ares........')
                            ->helperText('Cole aqui o link que foi gerado na sua integração da IntegraX')
                            ->required()
                            ->url()
                            ->columnSpan(1),
                        Toggle::make('status')
                            ->label('🟢 ATIVAR AUTOMAÇÃO')
                            ->helperText('Ative ou desative toda a automação de mensagens')
                            ->default(true)
                            ->columnSpan(1),
                    ])->columns(2),

                // Botão de Salvar (MAIS VISÍVEL)
                \Filament\Forms\Components\Actions::make([
                    \Filament\Forms\Components\Actions\Action::make('save_config')
                        ->label('💾 SALVAR CONFIGURAÇÃO')
                        ->color('success')
                        ->size('lg')
                        ->icon('heroicon-o-check-circle')
                        ->action('saveConfiguration')
                        ->extraAttributes([
                            'style' => 'width: 100%; margin: 20px 0; font-weight: bold; font-size: 16px;'
                        ])
                ]),

                // Seção de Introdução Simplificada
                Section::make('🚀 AUTOMAÇÃO INTELIGENTE DE MENSAGENS')
                ->description(new HtmlString('
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 12px; color: white; margin-bottom: 20px;">
                        <h3 style="margin: 0 0 15px 0; font-size: 18px; font-weight: bold;">✨ Transforme seu Cassino com Automação Inteligente!</h3>
                        <p style="margin: 0 0 15px 0; line-height: 1.6;">
                            Automatize o envio de mensagens personalizadas para seus jogadores em momentos estratégicos. 
                            Aumente o engajamento, incentive depósitos e melhore a experiência do usuário.
                        </p>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <a class="dark:text-white" 
                               style="
                                    font-size: 14px;
                                    font-weight: 600;
                                    padding: 12px 20px;
                                    background: linear-gradient(45deg, #ff6b6b, #ee5a24);
                                    border-radius: 8px;
                                    text-decoration: none;
                                    display: inline-flex;
                                    align-items: center;
                                    gap: 8px;
                               " 
                               href="https://app.integraflux.com/register-l?o=playfiver" 
                               target="_blank">
                                🎯 CRIAR CONTA INTEGRAX
                            </a>
                            <a class="dark:text-white" 
                               style="
                                    font-size: 14px;
                                    font-weight: 600;
                                    padding: 12px 20px;
                                    background: linear-gradient(45deg, #00b894, #00a085);
                                    border-radius: 8px;
                                    text-decoration: none;
                                    display: inline-flex;
                                    align-items: center;
                                    gap: 8px;
                               " 
                               href="https://api.whatsapp.com/send?phone=551132808396&text=Olá, gostaria de saber mais sobre a integração com a playfiver." 
                               target="_blank">
                                💬 SUPORTE WHATSAPP
                            </a>
                        </div>
                    </div>
                ')),

                // Seção de Exemplos Simplificada
                Section::make('💬 EXEMPLOS DE MENSAGENS QUE CONVERTEM')
                ->description('Veja exemplos práticos de mensagens que você pode personalizar:')
                ->schema([
                    \Filament\Forms\Components\Placeholder::make('message_examples')
                        ->content(new HtmlString('
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin: 20px 0;">
                                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 12px; color: white;">
                                    <h4 style="margin: 0 0 15px 0; font-size: 16px; font-weight: bold;">👤 APÓS O CADASTRO</h4>
                                    <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; font-style: italic;">
                                        "Se divirta na BETXZ, seu cadastro foi selecionado para receber um bonus de R$ 50,00 sem roll-over! Aproveite e deposite agora."
                                    </div>
                                </div>
                                
                                <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 20px; border-radius: 12px; color: white;">
                                    <h4 style="margin: 0 0 15px 0; font-size: 16px; font-weight: bold;">💳 APÓS GERAR O PIX</h4>
                                    <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; font-style: italic;">
                                        "BETXZ - Você vai receber um bonus incrível após o pagamento, corra antes que expira!"
                                    </div>
                                </div>
                            </div>
                        '))
                ]),

                // Seção de Benefícios Simplificada
                Section::make('📈 BENEFÍCIOS DA AUTOMAÇÃO')
                ->description('Veja como a automação pode transformar seu cassino:')
                ->schema([
                    \Filament\Forms\Components\Placeholder::make('benefits')
                        ->content(new HtmlString('
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin: 20px 0;">
                                <div style="text-align: center; padding: 20px; background: #fff3cd; border-radius: 12px; border: 1px solid #ffeaa7;">
                                    <div style="font-size: 32px; margin-bottom: 10px;">📈</div>
                                    <h5 style="margin: 0 0 10px 0; color: #856404;">Aumento de Conversão</h5>
                                    <p style="margin: 0; color: #856404; font-size: 14px;">Mensagens no momento certo aumentam depósitos</p>
                                </div>
                                
                                <div style="text-align: center; padding: 20px; background: #d4edda; border-radius: 12px; border: 1px solid #c3e6cb;">
                                    <div style="font-size: 32px; margin-bottom: 10px;">🎯</div>
                                    <h5 style="margin: 0 0 10px 0; color: #155724;">Engajamento</h5>
                                    <p style="margin: 0; color: #155724; font-size: 14px;">Mantenha jogadores ativos e engajados</p>
                                </div>
                                
                                <div style="text-align: center; padding: 20px; background: #f8d7da; border-radius: 12px; border: 1px solid #f5c6cb;">
                                    <div style="font-size: 32px; margin-bottom: 10px;">💰</div>
                                    <h5 style="margin: 0 0 10px 0; color: #721c24;">ROI Positivo</h5>
                                    <p style="margin: 0; color: #721c24; font-size: 14px;">Investimento que se paga rapidamente</p>
                                </div>
                            </div>
                        '))
                ]),
            ])
            ->statePath('data');
    }

    /**
     * Salvar configuração via API
     */
    public function saveConfiguration(): void
    {
        $this->validate([
            'data.integrax_url' => 'required|url',
            'data.status' => 'boolean'
        ]);

        try {
            // Criar uma requisição simulada para o controller da API
            $request = new \Illuminate\Http\Request();
            $request->merge([
                'integrax_url' => $this->data['integrax_url'],
                'status' => $this->data['status']
            ]);

            // Chamar diretamente o controller da API
            $controller = new AutomacaoIntegraXController();
            $response = $controller->store($request);

            if ($response->getStatusCode() === 200) {
                Notification::make()
                    ->title('✅ Configuração salva com sucesso!')
                    ->success()
                    ->send();
                
                // Recarregar configuração
                $this->mount();
            } else {
                Notification::make()
                    ->title('❌ Erro ao salvar configuração')
                    ->body('Verifique os dados e tente novamente.')
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('❌ Erro ao salvar configuração')
                ->body('Erro: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @return void
     */
    public function submit(): void
    {
        try {
            if(env('APP_DEMO')) {
                Notification::make()
                    ->title('Atenção')
                    ->body('Você não pode realizar está alteração na versão demo')
                    ->danger()
                    ->send();
                return;
            }

            $setting = AutomacaoIntegraX::first();
            if(!empty($setting)) {
                if($setting->update($this->data)) {
                    Notification::make()
                        ->title('SUAS CONFIGURAÇÕES FORAM ALTERADAS COM SUCESSO!')
                        ->body('A automação foi configurada com sucesso!')
                        ->success()
                        ->send();
                }
            }else{
                if(AutomacaoIntegraX::create($this->data)) {
                    Notification::make()
                        ->title('SUAS CONFIGURAÇÕES FORAM CRIADAS COM SUCESSO!')
                        ->body('A automação foi criada com sucesso!')
                        ->success()
                        ->send();
                }
            }

        } catch (Halt $exception) {
            Notification::make()
                ->title('Erro ao alterar dados!')
                ->body('Erro ao alterar dados!')
                ->danger()
                ->send();
        }
    }

    /**
     * Ação para ativar a automação
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('activate')
                ->label('Ativar Automação')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => $this->setting && !$this->setting->isActive())
                ->action(function () {
                    if ($this->setting) {
                        $this->setting->activate();
                        Notification::make()
                            ->title('Automação Ativada!')
                            ->body('A automação foi ativada com sucesso.')
                            ->success()
                            ->send();
                    }
                }),
            Action::make('deactivate')
                ->label('Desativar Automação')
                ->icon('heroicon-o-pause')
                ->color('danger')
                ->visible(fn () => $this->setting && $this->setting->isActive())
                ->action(function () {
                    if ($this->setting) {
                        $this->setting->deactivate();
                        Notification::make()
                            ->title('Automação Desativada!')
                            ->body('A automação foi desativada com sucesso.')
                            ->success()
                            ->send();
                    }
                }),
        ];
    }
}
