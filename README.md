# Introdução
Esta é uma aplicação CRUD de cadastro de pedidos e produtos, que possui seus métodos em rotas disponibilizadas via API.

# Sumário
    1. Informações do sistema
    2. links de apoio
    3. API
    4. Como instalar o projeto

# Informações do sistema
  - Lumen Lumen 9.0.2 (Laravel Components ^9.0)
  - PHP 8.1.5
  - Apache 2.4
  - MySQL 5.7


# links de apoio
Links de documentações e materiais de apoio

  - [Documentação Lumen](https://lumen.laravel.com/docs/9.x)
  - [documentação Laravel](https://laravel.com/docs/7.x)


# API

**Utilizando o Postaman**
Para requisições você pode utilizar o [POSTMAN](https://www.postman.com/downloads/), neste repositório há um pacote do postman com todas as requisições e parametros já configurados, para utilizar, você deve apenas importar o arquivo que está em "postman/CRUD_Lumen.postman_collection.json"

## Produtos
### **Listar**
Lista produtos cadastrados no banco de dados
```
rota: "/api/produtos"
Método: GET
Parametros: {
    name: [STING] (opcional),
    id: [array]|[Integer] (opcional),
    min_price: [FLOAT] (opcional),
    max_price: [FLOAT] (opcional)
}
```

### **Filtrar**
Lista produtos cadastrados no banco de dados, disponibilizado via método POST
```
rota: "/api/produtos_filtro"
Método: POST
Parametros: {
    name: [STING] (opcional),
    id: [array]|[Integer] (opcional),
    min_price: [FLOAT] (opcional),
    max_price: [FLOAT] (opcional)
}
```

### **Mostrar**
Exibe 1 produto especifico, o id do produto deve ser especificado na URL {id}.
```
rota: "/api/produtos/{id}"
Método: GET
Parametros: {
}
```

### **Cadastrar**
Rota para cadastro de produtos.

```
rota: "/api/produtos"
Método: POST
Parametros: {
    name: [STING],
    price: [FLOAT],
}
```

### **Editar**
Rota para edição de produtos.

```
rota: "/api/produtos"
Método: PUT
Parametros: {
    id: [ INTEGER ]
    name: [STING] (opcional),
    price: [FLOAT] (opcional),
}
```

### **Excluir**
Rota para Exclusão de produtos.

```
rota: "/api/produtos"
Método: DELETE
Parametros: {
    id: [ INTEGER ]
}
```

## Pedidos
### **Listar**
Lista Pedidos cadastrados no banco de dados (não retorna detalhando as info de cada item do pedido)

```
rota: "/api/pedidos"
Método: GET
Parametros: {
    min_price: [FLOAT] (opcional),
    max_price: [FLOAT] (opcional)
}
```

### **Mostrar**
Exibe 1 pedido especifico, detalhando cada produto adicionado a ele, o id do produto deve ser especificado na URL {id}.

```
rota: "/api/pedidos/{id}"
Método: GET
Parametros: {
}
```

### **Cadastrar**
Rota para cadastro de pedido, se não for informado a quantidade, por padrão é 1 e se não for informado o valor de desconto, por padrão é 0.
**Nota:** o valor de deconto pode ser numerico ou em %, exemplos ('100.30', '25', '30%', '15%'...)
**Nota 2:** Os valores totais nunca poderão ser menores que zero, em caso de 100% de desconto ou valor de desconto maior que de produto, o total será 0.
**Nota 3:** Os valores são salvos na tabela pedido_item para que se tenha o registro histórico, pois produtos podem sofrer alterações de preços com o tempo.

```
rota: "/api/pedidos"
Método: POST
Parametros: {
    produtos: [array]
}

Exemplo de array produtos:
produtos: [ 
    {
        id: [INTEGER],
        quantidade: [INTEGER] (opcional),
        desconto: [FLOAT]|[%] (opcional)
    }
]
```

### **Editar**
Rota para edição de produtos, se não for informado a quantidade, por padrão é 1 e se não for informado o valor de desconto e desconto_anterior, por padrão é 0.
**Nota:** o valor de deconto e desconto_anterior podem ser numericos ou em %, exemplos ('100.30', '25', '30%', '15%'...)
**Nota 2:** Os valores totais nunca poderão ser menores que zero, em caso de 100% de desconto ou valor de desconto maior que de produto, o total será 0.


```
rota: "/api/pedidos"
Método: PUT
Parametros: {
    id_pedido: [INTEGER],
    produtos: [array]
}

Exemplo de array produtos:
produtos: [ 
    {
        id: [INTEGER],
        desconto_anterior: [FLOAT]|[%],
        quantidade: [INTEGER] (opcional),
        desconto: [FLOAT]|[%] (opcional)
    }
]
```

### **Finalizar Pedido**
Rota para finalização de pedidos, após finalizar/fechar um pedido, não é mais possivel editar ou excluir ele.

```
rota: "/api/pedidos"
Método: PUT
Parametros: {
    id_pedido: [ INTEGER ]
}
```

### **Excluir**
Rota para Exclusão de pedidos, um pedido já finalizado não pode ser excluído.

```
rota: "/api/pedidos"
Método: DELETE
Parametros: {
    id_pedido: [ INTEGER ]
}
```

### **Excluir Item do pedido (AINDA NÃO IMPLEMENTADO)**
Rota para Exclusão de items do pedidos, um pedido já finalizado não pode ser alterado. (Ainda pendente de implementação, atualmente para remover você pode atualizar a quantidade do produto para 0)

```
rota: "/api/pedidos/item"
Método: DELETE
Parametros: {
    id_pedido: [ INTEGER ],
    id_produto: [ INTEGER ],
}
```

# Como instalar o projeto

Este guia de instalação não utilizará Docker, para poder configurar o ambiente sugiro o uso do [WAMP](https://www.wampserver.com/en/) / [LAMP](https://rockcontent.com/br/blog/lamp/) / [MAMP](https://www.mamp.info/en/mac/) (dependendo do seu sistema operacional, se estiver usando Windows, de uma olhada no [Laragon](https://laragon.org/) )

Você também precisará instalar:
  - git
  - git bash (Se utilizar Windows)
  - composer

## 1. Baixar dependencias
Você deve realizar a clonagem do projeto execute o seguinte comando:
```
git clone https://github.com/L-ivel96/api_lumen
```

Na raiz do projeto, execute o seguinte comando no terminal:
```
composer install
```

## 2. Configurar arquivo .env
1. Você deve criar o banco de dados local.
2. copiar o arquivo env.example, colar na raiz do projeto e renomear para .env
3. no arquivo .env configurar variáveis de banco de dados:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=
```

## 3. Gerar APP_KEY do .env
No arquivo .env vc pode adicionar um hash aleatório como chave

## 4. Configurar virtual Host

**Nota:** estou chamando meu projeto de "api.test" para o virtual host.

**NO Windows**, você deve ir até a pasta { caminho do seu PC }\System32\drivers\etc, no arquivo "hosts" Add linha:
```
127.0.0.1      api.test
```

Você deve localizar o arquivo de virtual host do apache e add as seguintes configurações:
```
# API
<VirtualHost *:80> 
    DocumentRoot "< caminho do projeto>/public/"
    ServerName api.test
    ServerAlias *.api.test
    <Directory "< caminho do projeto>/public/">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Caso queira configurar para acessar com HTTPS
```
# API
<VirtualHost *:443> 
    DocumentRoot "<caminho>/api_laravel_vuejs/api/public/"
    ServerName api.test
    ServerAlias *.api.test
    <Directory "<caminho>/api_laravel_vuejs/api/public/">
        AllowOverride All
        Require all granted
    </Directory>
    SSLEngine on
    SSLCertificateFile      < caminho da chave>/chave.crt
    SSLCertificateKeyFile   < caminho da chave>/chave.key
</VirtualHost>
```

## 5. Criando e atualizando tabelas no banco de dados
Na raiz do projeto, execute o seguinte comando no terminal:
```
php artisan migrate
```

## Author: 
**Levi Siqueira**: @L-ivel96 (https://github.com/L-ivel96/)

