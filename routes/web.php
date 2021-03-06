<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->group(['prefix' => 'api'], function () use ($router) {
    
    //Pedidos
    $router->get('pedidos', 'PedidosController@listar');
    $router->get('pedidos/{id}', 'PedidosController@mostrar');
    $router->post('pedidos', 'PedidosController@cadastrar');
    $router->put('pedidos', 'PedidosController@editar');
    $router->put('pedidos/finalizar', 'PedidosController@finalizar_pedido');
    $router->delete('pedidos', 'PedidosController@excluir');

    //Produtos
    $router->get('produtos',  'ProdutoController@listar');
    $router->get('produtos/{id}',  'ProdutoController@mostrar');
    $router->post('produtos', 'ProdutoController@cadastrar');
    $router->put('produtos', 'ProdutoController@editar');
    $router->delete('produtos', 'ProdutoController@excluir');
    $router->post('produtos_filtro', 'ProdutoController@listar');

});  