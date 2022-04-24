<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PedidoItem extends Model
{
    use SoftDeletes;

    protected $table = 'pedido_item';
    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'produto_id',
        'price',
        'desconto',
        'quantidade'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var string[]
     */
    protected $hidden = [
        
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedidos::class, 'id', 'pedido_id');
    }

    /*
        A relação não deve ser usada como base de informações do produto devido 
        a possiveis atualizações de preço dos produtos.
    */
    public function produto()
    {
        return $this->belongsTo(Pedidos::class, 'id', 'pedido_id');
    }
}
