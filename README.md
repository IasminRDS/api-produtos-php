# 🛒 API REST de Produtos (PHP)

![PHP](https://img.shields.io/badge/PHP-8-777BB4?logo=php&logoColor=white)
![SQLite](https://img.shields.io/badge/SQLite-003B57?logo=sqlite&logoColor=white)
[![testes](https://github.com/IasminRDS/api-produtos-php/actions/workflows/ci.yml/badge.svg)](https://github.com/IasminRDS/api-produtos-php/actions/workflows/ci.yml)
![License](https://img.shields.io/badge/licença-MIT-blue)

API REST de produtos escrita em **PHP puro** (sem framework), usando **PDO + SQLite** e **prepared statements** (proteção contra SQL Injection).

## ✨ Destaques

- 🔀 Roteamento próprio (sem framework) com `preg_match`
- 🔒 **Prepared statements** em todas as consultas — seguro contra SQL Injection
- 🗄️ **PDO + SQLite**: não precisa de servidor de banco (o banco é um arquivo)
- 🔢 Códigos HTTP corretos (`200`, `201`, `204`, `400`, `404`, `405`)
- ✅ Validação de entrada com mensagens de erro claras
- 📦 Camada de dados separada (`db.php`) da camada de rotas (`index.php`)

## 🚀 Como executar

Requer **PHP 8+** ([como instalar](https://www.php.net/downloads)).

```bash
git clone https://github.com/IasminRDS/api-produtos-php.git
```

Popular o banco com dados de exemplo (opcional):

```bash
php seed.php
```

Subir o servidor embutido do PHP:

```bash
php -S localhost:8000 index.php
```

A API fica em `http://localhost:8000`.

> O banco padrão é o `produtos.db` ao lado do `index.php`. Para apontar para
> outro arquivo, defina `PRODUTOS_DB` — é o que os testes usam.

## 🧪 Testes

```bash
php test_api.php          # 21 testes
```

Sem framework e sem dependência, como o resto do projeto: o script sobe o
servidor embutido do PHP contra um SQLite descartável, faz requisições HTTP
de verdade e derruba tudo no fim — então o que está sendo testado é a mesma
API que o cliente consome, com roteamento e códigos de status inclusos.

Além do CRUD e da validação, três testes cobrem justamente a afirmação de
segurança deste README: um `'; DROP TABLE produtos; --` enviado no campo
`nome` é gravado como texto e devolvido literal, e a tabela continua de pé.
Sem os *prepared statements*, esses testes falhariam.

## 📡 Endpoints

| Método | Rota | Descrição | Status |
|---|---|---|---|
| `GET` | `/produtos` | Lista todos | 200 |
| `GET` | `/produtos/{id}` | Retorna um | 200 / 404 |
| `POST` | `/produtos` | Cria | 201 / 400 |
| `PUT` | `/produtos/{id}` | Atualiza | 200 / 404 |
| `DELETE` | `/produtos/{id}` | Remove | 204 / 404 |

## 💻 Exemplos (curl)

Criar:

```bash
curl -X POST http://localhost:8000/produtos -H "Content-Type: application/json" -d '{"nome": "Notebook", "preco": 3500, "estoque": 10}'
```

Listar:

```bash
curl http://localhost:8000/produtos
```

Atualizar:

```bash
curl -X PUT http://localhost:8000/produtos/1 -H "Content-Type: application/json" -d '{"nome": "Notebook Pro", "preco": 4200, "estoque": 8}'
```

Remover:

```bash
curl -X DELETE http://localhost:8000/produtos/1
```

## 📁 Estrutura

```
api-produtos-php/
├── index.php   # front controller: roteamento + endpoints
├── db.php      # conexão PDO + criação da tabela
├── seed.php    # popula dados de exemplo
└── README.md
```

## 💡 Conceitos demonstrados

- Arquitetura REST e semântica de métodos HTTP em PHP
- Segurança: prepared statements contra SQL Injection
- PDO como camada de acesso a dados
- Roteamento manual (front controller)

## 📄 Licença

MIT — veja [LICENSE](./LICENSE).

---

Feito por **Iasmin Ribeiro de Souza** · [LinkedIn](https://www.linkedin.com/in/iasmin-ribeiro-de-souza-033536401) · [GitHub](https://github.com/IasminRDS)
