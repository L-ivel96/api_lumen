<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Produto;
use App\Models\Pedidos;
use App\Models\PedidoItem;
use Carbon\Carbon;
use Exception;
use phpDocumentor\Reflection\Types\Boolean;
use phpDocumentor\Reflection\Types\This;

class PedidosController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    
    public function cadastrar(Request $request)
    {
    	try
    	{
            $erros = array();
            
            $produtos = $request->produtos;

            if(empty($produtos) || !is_array($produtos) ) {
                $resposta = array(
                    'cadastro' => false,
                    'mensagem' => "O parametro produtos deve conter uma lista (array) com os produtos"
                );
    
                return response()->json($resposta, 406);
            }

            $dados_produtos = $this->mescla_produtos_iguais($produtos);
            $produtos = $dados_produtos['produtos'];
            $erros = $erros = array_merge($erros, $dados_produtos['erros']);

	    	$dados_pedido = $this->totais_pedido($produtos);
            $valor_final_pedido = 
                $dados_pedido['total_itens'] - $dados_pedido['total_descontos'] < 0 
                ? 0 
                : $dados_pedido['total_itens'] - $dados_pedido['total_descontos'];

            $dados = array(
                'amount' => $valor_final_pedido,
                'finalizado' => 0
            );
	    	
            $pedido = Pedidos::create($dados);

            //Faz o reprocessamento para criar os itens do pedido
            $this->totais_pedido($produtos, $pedido['id']);

            $dados_itens = $pedido->pedido_itens;
            $dados_itens_filtrados = array();

            foreach($dados_itens as $item) {
                $valor_total = ($item['price'] * $item['quantidade']) - ($item['desconto'] * $item['quantidade']);
                $valor_total = $valor_total < 0 ? 0 : $valor_total;
                $dados_itens_filtrados[] = array(
                    'id' => $item['id'],
                    'product_id' => $item['produto_id'],
                    'quantity' => $item['quantidade'],
                    'amount' => round($valor_total,2),
                );
            }

            //Normaliza dados e mescla informações do pedido
            $pedido_normalizado = array(
                'id' => $pedido['id'],
                'amount' => round($pedido['amount'], 2),
                'created_at' => $pedido['created_at']->toDateTimeString(),
                'items' => $dados_itens_filtrados
            );
            
            $resposta = array(
                'cadastro' => true,
                'mensagem' => "Pedido criado com sucesso!",
                'pedido' => $pedido_normalizado
            );

            if(!empty($dados_pedido['erros'])) {
                $erros = array_merge($erros, $dados_pedido['erros']);
            }

            if(!empty($erros)) {
                $resposta['erros'] = $erros;
            }

            return response()->json($resposta);

    	}
    	catch(\Exception $e)
    	{
    		$resposta = array('erro' => true, 'mensagem' => $e->getLine() . " | ". $e->getMessage());
            return response()->json($resposta, 500);
    	}
    }

    public function totais_pedido($produtos, $id_pedido = null) {
        $total_itens = 0;
        $total_descontos = 0;

        $erros = array();
        $contador = 0;

        foreach($produtos as $produto) {
            if(!isset($produto['id'])) {
                $erros[] = "Exitsem produtos que não estão corretamente estruturado, o parametro id é obrigatório, realizar uma atualização do pedido com o produto desejado";
                $contador++;
                continue;
            }

            $item = Produto::find($produto['id']);

            if(empty($item)) {
                $erros[] = "O produto com id {$produto['id']} não está disponivel ou não existe";
                $contador++;
                continue;
            }

            $valor_produto = $item['price'];
            $quantidade_produto = $produto['quantidade'] ?? 1;

            $total_itens += $valor_produto * (int) $quantidade_produto;

            $dados_desconto = $this->calcula_desconto_item($produto, $valor_produto, $quantidade_produto, $contador);
            $total_descontos += $dados_desconto['total_descontos'];
            $erros = !empty($dados_desconto) ? array_merge($erros, $dados_desconto['erros']) : $erros;

            if(!is_null($id_pedido)) {
                PedidoItem::updateOrInsert(
                    [
                        'produto_id' => $item['id'], 
                        'pedido_id' => $id_pedido, 
                        'desconto' => $dados_desconto['descontos']
                    ],
                    [
                        'price' => round($valor_produto,2), 
                        'desconto' => round($dados_desconto['descontos'], 2), 
                        'quantidade' => $quantidade_produto
                    ]
                );
            }

            $contador++;
        }

        return array(
            'total_itens' => round($total_itens,2),
            'total_descontos' => round($total_descontos, 2),
            'erros' => $erros
        );
    }

    public function calcula_desconto_item(
        $produto, 
        $valor_produto,
        $quantidade_produto,
        $posicao
    ) {
        $total_descontos = 0;
        $valor_desconto = 0;
        $erros = [];

        if(!empty($produto['desconto'])) {
            $erro_desconto = true;
            $desconto = $produto['desconto'];

            if(strpos($desconto, "%") !== false) {
                $desconto_arrumado = str_replace("%", "", $desconto);
                if(is_numeric($desconto_arrumado)) {
                    $valor_desconto =  $desconto_arrumado;
                    $desconto = ($valor_desconto/100) * $valor_produto * $quantidade_produto;
                }
            }

            if(is_numeric($desconto)) {
                $valor_desconto = $desconto;
                $total_descontos += $desconto;
                $erro_desconto = false;
            }

            if($erro_desconto) {
                $erros[] = "Ocorreu um erro no desconto do produto com id {$produto['id']}, você pode remover o produto e adicionar para novamente para aplicação do desconto, o parametro desconto deve ser numerico no padrão ('10.50') ou em porcentagem ('20%')";
            }
        }

        return array(
            'descontos' => $valor_desconto,
            'total_descontos' => $total_descontos,
            'erros' => $erros
        ); 
    }

    public function corrige_quantidade($quantidade, $posicao = 0) {
        $erros = array();
        $quantidade_produto = $quantidade;
            
        if(!is_numeric($quantidade_produto)) {
            $quantidade_produto = 1;
            $erros[] = "quantidade de produtos do produto na posição $posicao inválido, a quantidade deve ser um valor numerico e inteiro, foi considerado a quantidade como 1";
        }

        return array(
            'quantidade' => $quantidade_produto,
            'erros' => $erros
        ); 
    }

    public function mescla_produtos_iguais($produtos) {
        $erros = array();
        //id_produto-desconto => posicao no array
        $lista_produtos = array();
        $lista_produtos_arrumados = array();
        
        $produtos_arrumados = array();
        $contador = 0;
        $contador_loop = 0;

        foreach($produtos as $chave => $produto) {
            if(!isset($produto['id'])) {
                $erros[] = "O produto na posição $contador_loop não está corretamente estruturado, o parametro id é obrigatório, realizar uma atualização do pedido com o produto desejado";
                $contador_loop++;
                continue;
            }

            $desconto_produto = $produto['desconto'] ?? "";
            $chave_arrumado = $produto['id'] . "-" . $desconto_produto;

            if (array_key_exists($chave_arrumado, $lista_produtos)) {
                $pos_arrumado = $lista_produtos_arrumados[$chave_arrumado];
                
                $produtos_arrumados[$pos_arrumado]['quantidade'] = $this->corrige_quantidade($produtos_arrumados[$pos_arrumado]['quantidade'] ?? 1, $contador_loop)['quantidade'];
                $produtos_arrumados[$pos_arrumado]['quantidade'] +=  $this->corrige_quantidade($produto['quantidade'] ?? 1, $contador_loop)['quantidade'];
                $produtos_arrumados[$pos_arrumado]['desconto'] = $produtos_arrumados[$pos_arrumado]['desconto'] ?? 0;
                $produtos_arrumados[$pos_arrumado]['desconto'] +=  $produto['desconto'] ?? 0;
                $contador_loop++;
                continue;
            }
            
            $produtos_arrumados[$contador] = $produto;

            $lista_produtos[$chave_arrumado] = $chave;
            $lista_produtos_arrumados[$chave_arrumado] = $contador;
            $contador++;
            $contador_loop++;
        }

        return array(
            'produtos' => $produtos_arrumados,
            'erros' => $erros
        ); 
    }


}