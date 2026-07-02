# Documentação do Sistema de Biblioteca Escolar

## 1. Visão Geral

Este projeto é uma aplicação web em PHP para gestão de uma biblioteca escolar. O sistema permite cadastrar livros, controlar empréstimos, registrar devoluções, gerenciar reservas e visualizar relatórios básicos de uso.

A implementação atual funciona em ambiente local com XAMPP e utiliza SQLite como banco de dados, o que facilita a execução sem necessidade de um servidor MySQL separado.

---

## 2. Objetivo do Projeto

O objetivo principal é organizar o funcionamento da biblioteca escolar, oferecendo um fluxo simples para:

- cadastrar e manter o acervo de livros;
- controlar usuários e permissões;
- registrar empréstimos e devoluções;
- controlar reservas;
- acompanhar livros com estoque baixo e empréstimos atrasados.

---

## 3. Status Atual da Implementação

A aplicação já possui as funcionalidades básicas abaixo:

- autenticação com login e logout;
- controle de perfis: Administrador, Bibliotecário, Aluno e Visitante;
- painel inicial com estatísticas simples;
- cadastro, edição e exclusão de livros;
- registro de empréstimos e devoluções;
- controle de reservas;
- relatórios de empréstimos ativos, atrasados, estoque baixo e usuários bloqueados;
- instalação inicial do banco de dados via script de configuração.

---

## 4. Tecnologias Utilizadas

- PHP
- HTML
- CSS
- JavaScript (mínimo, para interações simples)
- SQLite
- XAMPP / Apache

---

## 5. Estrutura de Pastas

- config.php: configurações gerais do sistema, como nome do site e limites de empréstimo.
- setup.php: cria as tabelas do banco e insere dados iniciais.
- index.php: página inicial com catálogo rápido.
- login.php: tela de autenticação.
- dashboard.php: painel com resumo estatístico.
- books.php: cadastro e manutenção de livros.
- loans.php: registro de empréstimos e devoluções.
- reservations.php: gestão de reservas.
- reports.php: relatórios administrativos.
- includes/: arquivos compartilhados de autenticação, banco de dados, funções utilitárias e layout.
- data/: diretório para o arquivo de banco SQLite.
- css/: estilos do sistema.

---

## 6. Regras de Negócio

Algumas regras implementadas no fluxo atual são:

- limite de até 2 empréstimos simultâneos por usuário;
- prazo padrão de 30 dias para devolução;
- usuários com empréstimos atrasados podem ser bloqueados ao realizar a devolução;
- o estoque do livro é atualizado automaticamente ao emprestar ou devolver;
- reservas podem ser cumpridas por administradores ou bibliotecários.

---

## 7. Modelagem do Banco de Dados

O banco é criado automaticamente pelo script setup.php e contém as tabelas principais abaixo:

- roles: perfis de usuário;
- users: dados dos usuários cadastrados;
- books: catálogo de livros;
- loans: histórico e controle de empréstimos;
- reservations: reservas de livros.

Campos importantes de livros:

- título
- autor
- categoria
- ISBN
- editora
- ano
- quantidade
- estante
- código interno

---

## 8. Perfis de Usuário

### Administrador
- acesso total ao sistema;
- pode gerenciar livros, empréstimos, reservas e relatórios.

### Bibliotecário
- pode gerenciar empréstimos, livros e relatórios.

### Aluno
- pode consultar o catálogo, reservar livros e ver seus próprios registros.

### Visitante
- acesso mais limitado, geralmente para navegação pública do catálogo.

---

## 9. Como Executar Localmente

### Pré-requisitos

- XAMPP instalado com Apache e PHP ativos;
- extensão SQLite habilitada no PHP;
- navegador web.

### Passos

1. Copie a pasta do projeto para a pasta htdocs do XAMPP.
2. Inicie o Apache no painel do XAMPP.
3. Acesse a URL: http://localhost/biblioteca/setup.php
4. O script criará o banco de dados e inserirá dados iniciais.
5. Acesse http://localhost/biblioteca/login.php para entrar no sistema.

---

## 10. Credenciais Iniciais

O script setup.php cria usuários de exemplo com as seguintes credenciais:

- Administrador: admin@biblioteca.local / admin123
- Bibliotecário: bibliotecario@biblioteca.local / biblio123
- Aluno: aluno@biblioteca.local / aluno123
- Visitante: teste@biblioteca.local / teste123

---

## 11. Fluxos Principais

### Login

O usuário entra com email e senha. Se as credenciais forem válidas, a sessão é iniciada e ele é redirecionado para o painel.

### Cadastro de livros

Usuários com permissão de administrador ou bibliotecário podem incluir novos livros com dados básicos e controle de quantidade.

### Empréstimos

Para registrar um empréstimo, o usuário escolhe um usuário e um livro disponível. O sistema reduz a quantidade em estoque e cria um registro de empréstimo com data de devolução prevista.

### Devolução

Ao devolver um livro, o sistema atualiza o empréstimo como concluído e devolve uma unidade ao estoque.

### Reservas

Usuários autenticados podem reservar livros. Administradores e bibliotecários podem cumprir a reserva posteriormente.

### Relatórios

O painel de relatórios exibe empréstimos ativos, empréstimos atrasados, estoque baixo e usuários bloqueados.

---

## 12. Pontos de Extensão

O projeto está em uma fase inicial, mas já está preparado para evoluir com melhorias como:

- cadastro público de usuários;
- recuperação de senha;
- busca avançada de livros;
- exportação de relatórios em PDF;
- upload de capa de livros;
- histórico detalhado de movimentações;
- notificações por email ou tela.

---

## 13. Observações Importantes

- Se o banco ainda não existir, o sistema exibirá uma mensagem indicando que é necessário executar setup.php.
- O arquivo de banco SQLite fica em data/library.db.
- Para alterar o comportamento do sistema, confira config.php.

---

## 14. Resumo

Este projeto oferece uma base funcional para uma biblioteca escolar, com foco em simplicidade, facilidade de execução local e possibilidade de expansão futura. Ele já permite o uso prático do fluxo principal de uma biblioteca: cadastro de livros, empréstimos, devoluções, reservas e relatórios básicos.