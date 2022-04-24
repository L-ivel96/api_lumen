<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pedidos extends Model
{
    use SoftDeletes;

    protected $table = 'pedidos';
    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'amount'
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

    public function pedido_itens()
    {
        return $this->hasMany(PedidoItem::class, 'pedido_id', 'id');
    }

    public static function pedido_item_com_total($id_pedido)
    {
        $pedido = Pedidos::find($id_pedido);

        $dados_itens = $pedido->pedido_itens;
        $dados_itens_filtrados = array();

        foreach($dados_itens as $item) {
            $valor_total = ($item['price'] * $item['quantidade']) - ($item['desconto'] * $item['quantidade']);
            $valor_total = $valor_total < 0 ? 0 : $valor_total;
            $dados_itens_filtrados[] = array(
                'id' => (int) $item['id'],
                'product_id' => (int) $item['produto_id'],
                'quantity' => (int) $item['quantidade'],
                'amount' => (float) round($valor_total,2),
            );
        }

        return $dados_itens_filtrados;
    }

    public static function atualiza_total_pedido($id_pedido)
    {
        $pedido = Pedidos::find($id_pedido);

        $dados_itens = $pedido->pedido_itens;
        $total_pedido = 0;

        foreach($dados_itens as $item) {
            $valor_total = ($item['price'] * $item['quantidade']) - ($item['desconto'] * $item['quantidade']);
            $valor_total = $valor_total < 0 ? 0 : $valor_total;
            $total_pedido += $valor_total;
        }

        $pedido->amount = $total_pedido;
        $pedido->save();

        return true;

    }
}
