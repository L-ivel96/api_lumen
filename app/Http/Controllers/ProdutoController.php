<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Produto;
use Carbon\Carbon;
use Exception;

class ProdutoController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function mostrar(Request $request, $id)
    {
        try {
            $dados = Produto::where("id", "=", $id)->first();
            
            //Processo de filtragem de dados a serem expostos e no formato desajado
            $resposta = is_null($dados) ? array() : array(
                'id' => $dados['id'],
                'name' => $dados['name'],
                'price' => $dados['price'],
                'created_at' => $dados['created_at']->toDateTimeString(),
            );
            
            return response()->json($resposta);
        }
        catch(Exception $e) {
            $resposta = array('erro' => true, 'mensagem' => $e->getMessage());
            return response()->json($resposta, 500);
        }
    }

    public function listar(Request $request)
    {
        $dados = $this->buscar($request);

        if(isset($dados['erro']) && $dados['erro']) {
            $resposta = $dados;
            return response()->json($resposta, 500);
        }

         //Processo de filtragem de dados a serem expostos e no formato desajado
         $resposta = array();
            
         foreach($dados as $produto) {
             $linha = array(
                 'id' => $produto['id'],
                 'name' => $produto['name'],
                 'price' => $produto['price'],
                 'created_at' => $produto['created_at']->toDateTimeString(),
             );
             $resposta[] = $linha;
         }

         return response()->json($resposta);
    }

    public function buscar(Request $request)
    {
        try
    	{
    		$lista_produto = null;
            $query = Produto::select();

	    	if(!empty($request->name)) {
                    $nome_like = '%'.$request->name.'%';
                    $query->where("name", "like", $nome_like);
	    	}
            
            if(!empty($request->id)) {
                if(is_array($request->id)) {
                    $query->whereIn("id", $request->id);
                }
                else {
                    $query->where("id", "=", $request->id);
                }
            }
            
            if(!empty($request->min_price) && is_numeric($request->min_price)) {
                $query->where("price", ">=", $request->min_price);
            }
            
            if(!empty($request->max_price) && is_numeric($request->min_price)) {
                $query->where("price", "<=", $request->max_price);
            }

            $lista_produto = $query->get();

            return $lista_produto;
    	}
    	catch(\Exception $e)
    	{
    		return array('erro' => true, 'mensagem' => $e->getMessage());
    	}
    }

    public function cadastrar(Request $request)
    {
    	try
    	{
            //formatação
            $price = $request->price;

            if(empty($request->name) || empty($price) || !is_numeric($price) ) {
                $resposta = array(
                    'cadastro' => false,
                    'mensagem' => "Os parametros name({$request->name}) e price({$request->price}) são obrigatórios"
                );
    
                return response()->json($resposta, 406);
            }

	    	$dados = array(
	    		"name" => $request->name,
	    		"price" => $price
	    	);
	    	
            $produto = Produto::create($dados);
            
            $resposta = array(
                'cadastro' => true,
                'mensagem' => "Produto cadastrado com sucesso!",
                'produto' => $produto
            );

            return response()->json($resposta);

    	}
    	catch(\Exception $e)
    	{
    		$resposta = array('erro' => true, 'mensagem' => $e->getMessage());
            return response()->json($resposta, 500);
    	}
    }

    public function editar(Request $request)
    {
    	try
    	{
            //formatação
            $valor = $request->price;
            $dados = array();

    		if(
    			!is_numeric($request->id) || empty($request->id)
    		) {
                $resposta = array(
                    'atualizado' => false,
                    'mensagem' => "O id é um parametro obrigatório"
                );
    
                return response()->json($resposta, 406);
    		}

            if(!empty($request->name)) {
                $dados['name'] = $request->name;
            }

            if(!empty($valor) && is_numeric($valor)) {
                $dados['price'] = $valor;
            }
            
            $resposta = array();
            if(!empty($dados)) {
                $update = Produto::where('id', $request->id)->update($dados);
                $resposta = array(
                    'atualizado' => $update,
                    'mensagem' => $update ? "Produto atualizado com sucesso!" : "O produto especificado não foi encontrado."
                );
            }
            else {
                $resposta = array(
                    'atualizado' => false,
                    'mensagem' => "Nenhum dado foi enviado para atualizar!"
                );
                return response()->json($resposta, 406);
            }

            return response()->json($resposta);
    	}
    	catch(\Exception $e)
    	{
    		$resposta = array('erro' => true, 'mensagem' => $e->getMessage());
            return response()->json($resposta, 500);
    	}

    }

    public function excluir(Request $request)
    {
    	try
    	{
    		if(!is_numeric($request->id) || empty($request->id)) {
    			throw new \Exception("O id é um parametro obrigatório)");
    		}

    		$excluir = Produto::where('id', $request->id)->delete();
            
            $resposta = array(
                'excluido' => $excluir,
                'mensagem' => $excluir ? "Produto excluido com sucesso!" : "O produto especificado não existe."
            );

            return response()->json($resposta);
    	}
    	catch(\Exception $e)
    	{
    		$resposta = array('erro' => true, 'mensagem' => $e->getMessage());
            return response()->json($resposta, 500);
    	}
    }

}
