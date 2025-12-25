<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomacaoIntegraX extends Model
{
    protected $table = 'automacao_integrax';
    
    protected $fillable = [
        'integrax_url',
        'status'
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Verifica se a automação está ativa
     */
    public function isActive(): bool
    {
        return $this->status === true;
    }

    /**
     * Ativa a automação
     */
    public function activate(): void
    {
        $this->update(['status' => true]);
    }

    /**
     * Desativa a automação
     */
    public function deactivate(): void
    {
        $this->update(['status' => false]);
    }
} 