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

            //Normaliza dados e mescla informa????es do pedido
            $pedido_normalizado = array(
                'id' => (int) $pedido['id'],
                'amount' => (float) round($pedido['amount'], 2),
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

    public function editar(Request $request) 
    {
        try
    	{
            $erros = array();
            
            $produtos = $request->produtos;
            $id_pedido = $request->id_pedido;

            if(empty($id_pedido) || empty($produtos) || !is_array($produtos) ) {
                $resposta = array(
                    'erro' => false,
                    'mensagem' => "Os parametros id_pedido e produtos s??o obrigat??rios, produtos deve conter uma lista (array) com os produtos"
                );
    
                return response()->json($resposta, 406);
            }

            $pedido = Pedidos::find($id_pedido);

            if(!$pedido) {
                $resposta = array(
                    'erro' => true,
                    'mensagem' => "O Pedido ($id_pedido) n??o foi encontrado"
                );
    
                return response()->json($resposta, 404);
            }

            if($pedido->finalizado) {
                $resposta = array(
                    'erro' => true,
                    'mensagem' => "O Pedido ($id_pedido) j?? est?? finalizado, n??o ?? possivel editar"
                );
    
                return response()->json($resposta, 406);
            }
            
            $contador = 0;
	    	foreach($produtos as $produto) {
                if(empty($produto['id']) || !is_numeric($produto['id'])) {
                    $erros[] = "O produto na posi????o $contador n??o est?? corretamente estruturado. id ?? um parametro obrigat??rio e deve ser numerico para inclus??o de novos registros";
                    continue;
                }
                
                $produto_bd = Produto::find($produto['id']);

                $item = array(
                    'desconto' => $produto['desconto'] ?? 0
                );

                if(!$produto_bd) {
                    $erros[] = "O produto na posi????o $contador n??o est?? dispon??vel ou n??o existe";
                    continue;
                }

                $desconto_anterior = $produto['desconto_anterior'] ?? 0;
                $desconto = $this->calcula_desconto_item($item, $produto_bd->price, 1, $contador)['descontos'];
                $id_linha = $produto['id'] ?? 0;
                $dados_quantidade = $this->corrige_quantidade($produto['quantidade'], $contador);
                $quantidade = $dados_quantidade['quantidade'];
                $erros = array_merge($erros, $dados_quantidade['erros']);

                $item_linha = PedidoItem::where('produto_id', $id_linha)
                    ->where('pedido_id', $id_pedido)
                    ->where('desconto', $desconto_anterior)
                    ->first();
                
                $item_linha_existente = PedidoItem::where('produto_id', $id_linha)
                ->where('pedido_id', $id_pedido)
                ->where('desconto', $desconto)
                ->first();
                
                if($item_linha) {
                    $item_linha->desconto = $desconto;
                    $item_linha->quantidade = $quantidade;

                    $item_linha->save();
                }
                else if($item_linha_existente) {
                    $item_linha_existente->desconto = $desconto;
                    $item_linha_existente->quantidade = $quantidade;

                    $item_linha_existente->save();
                }
                else {
                    $novo_item = new PedidoItem;
                    $novo_item->produto_id = $produto['id'];
                    $novo_item->pedido_id = $id_pedido;
                    $novo_item->price = $produto_bd['price'];
                    $novo_item->desconto = $desconto;
                    $novo_item->quantidade = $quantidade;

                    $novo_item->save();
                }

                $contador++;
            }
            
            Pedidos::atualiza_total_pedido($pedido->id);

            $pedido = Pedidos::find($id_pedido);

            $dados_itens_filtrados = Pedidos::pedido_item_com_total($id_pedido);

            //Normaliza dados e mescla informa????es do pedido
            $pedido_normalizado = array(
                'id' => (int) $pedido['id'],
                'amount' => (float) round($pedido['amount'], 2),
                'created_at' => $pedido['created_at']->toDateTimeString(),
                'items' => $dados_itens_filtrados
            );
            
            $resposta = array(
                'atualizado' => true,
                'mensagem' => "Pedido atualizado com sucesso!",
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

    public function totais_pedido($produtos, $id_pedido = null) 
    {
        $total_itens = 0;
        $total_descontos = 0;

        $erros = array();
        $contador = 0;

        foreach($produtos as $produto) {
            if(!isset($produto['id'])) {
                $erros[] = "Exitsem produtos que n??o est??o corretamente estruturado, o parametro id ?? obrigat??rio, realizar uma atualiza????o do pedido com o produto desejado";
                $contador++;
                continue;
            }

            $item = Produto::find($produto['id']);

            if(empty($item)) {
                $erros[] = "O produto com id {$produto['id']} n??o est?? disponivel ou n??o existe";
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
    ) 
    {
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
                $erros[] = "Ocorreu um erro no desconto do produto com id {$produto['id']}, voc?? pode remover o produto e adicionar para novamente para aplica????o do desconto, o parametro desconto deve ser numerico no padr??o ('10.50') ou em porcentagem ('20%')";
            }
        }

        return array(
            'descontos' => $valor_desconto,
            'total_descontos' => $total_descontos,
            'erros' => $erros
        ); 
    }

    public function corrige_quantidade($quantidade, $posicao = 0) 
    {
        $erros = array();
        $quantidade_produto = $quantidade;
            
        if(!is_numeric($quantidade_produto)) {
            $quantidade_produto = 1;
            $erros[] = "quantidade de produtos do produto na posi????o $posicao inv??lido, a quantidade deve ser um valor numerico e inteiro, foi considerado a quantidade como 1";
        }

        return array(
            'quantidade' => $quantidade_produto,
            'erros' => $erros
        ); 
    }

    public function mescla_produtos_iguais($produtos, $campos_obrigatorios = null) {
        $erros = array();
        //id_produto-desconto => posicao no array
        $lista_produtos = array();
        $lista_produtos_arrumados = array();
        
        $produtos_arrumados = array();
        $contador = 0;
        $contador_loop = 0;

        foreach($produtos as $chave => $produto) {
            $campos_obrigatorios = $campos_obrigatorios ?? array("id");

            foreach($campos_obrigatorios as $campo) {
                if(!isset($produto[$campo])) {
                    $erros[] = "O produto na posi????o $contador_loop n??o est?? corretamente estruturado, o parametro $campo ?? obrigat??rio, realizar uma atualiza????o do pedido com o produto desejado";
                    $contador_loop++;
                    continue 2;
                }
            }

            $desconto_produto = $produto['desconto'] ?? "";
            $chave_arrumado = $produto['id'] . "-" . $desconto_produto;

            if (array_key_exists($chave_arrumado, $lista_produtos)) {
                $pos_arrumado = $lista_produtos_arrumados[$chave_arrumado];
                
                $produtos_arrumados[$pos_arrumado]['quantidade'] = $this->corrige_quantidade($produtos_arrumados[$pos_arrumado]['quantidade'] ?? 1, $contador_loop)['quantidade'];
                $produtos_arrumados[$pos_arrumado]['quantidade'] +=  $this->corrige_quantidade($produto['quantidade'] ?? 1, $contador_loop)['quantidade'];
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

    public function excluir(Request $request)
    {
    	try
    	{
    		if(!is_numeric($request->id_pedido) || empty($request->id_pedido)) {
    			throw new \Exception("O id_pedido ?? um parametro obrigat??rio)");
    		}
            $pedido = Pedidos::find($request->id_pedido);

            if(!$pedido) {
                $resposta = array('erro' => true, 'mensagem' => "O Pedido ({$request->id_pedido}) n??o existe");
                return response()->json($resposta);
            }

            if($pedido->finalizado) {
                $resposta = array('erro' => true, 'mensagem' => "O Pedido ({$request->id_pedido}) j?? est?? finalizado");
                return response()->json($resposta);
            }

            $excluir_relacionados = PedidoItem::where('pedido_id', $request->id_pedido)->delete();
            $pedido->delete();
            
            $resposta = array(
                'excluido' => true,
                'mensagem' => "Pedido excluido com sucesso!"
            );

            return response()->json($resposta);
    	}
    	catch(\Exception $e)
    	{
    		$resposta = array('erro' => true, 'mensagem' => $e->getMessage());
            return response()->json($resposta, 500);
    	}
    }

    public function finalizar_pedido(Request $request)
    {
    	try
    	{
    		if(!is_numeric($request->id_pedido) || empty($request->id_pedido)) {
    			throw new \Exception("O id_pedido ?? um parametro obrigat??rio)");
    		}
            $pedido = Pedidos::find($request->id_pedido);

            if(!$pedido) {
                $resposta = array('erro' => true, 'mensagem' => "O Pedido ({$request->id_pedido}) n??o existe");
                return response()->json($resposta);
            }

            if($pedido->finalizado) {
                $resposta = array('erro' => true, 'mensagem' => "O Pedido ({$request->id_pedido}) j?? est?? finalizado");
                return response()->json($resposta);
            }

            $pedido->finalizado = 1;
            $pedido->save();
            
            $resposta = array(
                'mensagem' => "Pedido finalizado com sucesso!"
            );

            return response()->json($resposta);
    	}
    	catch(\Exception $e)
    	{
    		$resposta = array('erro' => true, 'mensagem' => $e->getMessage());
            return response()->json($resposta, 500);
    	}
    }

    public function mostrar(Request $request, $id)
    {
        try {

            $pedido = Pedidos::find($id);
            
            if(!$pedido) {
                $resposta = array('erro' => true, 'mensagem' => "O Pedido ({$id}) n??o existe");
                return response()->json($resposta);
            }

            $dados_itens_filtrados = Pedidos::pedido_item_com_total($pedido->id);

            //Normaliza dados e mescla informa????es do pedido
            $pedido_normalizado = array(
                'id' => $pedido['id'],
                'amount' => (float) round($pedido['amount'], 2),
                'finalizado' => (bool) $pedido['finalizado'],
                'created_at' => $pedido['created_at']->toDateTimeString(),
                'items' => $dados_itens_filtrados
            );
            
            return response()->json($pedido_normalizado);
        }
        catch(Exception $e) {
            $resposta = array('erro' => true, 'mensagem' => $e->getMessage());
            return response()->json($resposta, 500);
        }
    }

    public function listar(Request $request)
    {
        $query = Pedidos::select();

        if(!empty($request->min_price) && is_numeric($request->min_price)) {
            $query->where("price", ">=", $request->min_price);
        }
        
        if(!empty($request->max_price) && is_numeric($request->min_price)) {
            $query->where("price", "<=", $request->max_price);
        }

        $dados = $query->get();

        if(!$dados) {
            $resposta = array('erro' => true, 'mensagem' => "Nenhum pedido encontrado");
            return response()->json($resposta);
        }

         //Processo de filtragem de dados a serem expostos e no formato desajado
         $resposta = array();
            
         foreach($dados as $produto) {
             $linha = array(
                 'id' => $produto['id'],
                 'amount' => (float) $produto['amount'],
                 'finalizado' => (bool) $produto['finalizado'],
                 'created_at' => $produto['created_at']->toDateTimeString(),
             );
             $resposta[] = $linha;
         }

         return response()->json($resposta);
    }


}
