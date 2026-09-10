# Sistema de Biblioteca Escolar
## Visão Geral
Aplicação web em PHP para gestão de uma biblioteca escolar. O sistema centraliza catálogo, usuários, empréstimos, devoluções, reservas e relatórios, com acesso controlado por sessão e perfil de usuário.

O projeto foi desenvolvido para execução local em ambiente PHP, atualmente configurado para XAMPP e SQLite. A migração para MySQL em uma VM Linux é uma pendência para a entrega de infraestrutura.

## Objetivo
Permitir que a equipe da biblioteca administre o acervo e os empréstimos, enquanto alunos e visitantes consultam livros, acompanham suas reservas e visualizam o próprio histórico.

## Tecnologias
- Backend: PHP com PDO.
- Frontend: HTML, CSS e JavaScript.
- Banco atual: SQLite em `data/library.db`.
- Ambiente atual: Apache/PHP do XAMPP.
- Armazenamento de arquivos: capas, PDFs e fotos de perfil em `uploads/`.

## Perfis de Acesso
| Perfil | Permissões principais |
|---|---|
| Administrador | Acesso administrativo ao catálogo, empréstimos, reservas, relatórios e dados gerais. |
| Bibliotecário | Gestão de livros, empréstimos, devoluções, reservas e relatórios. |
| Aluno | Consulta de catálogo, perfil, histórico de empréstimos e solicitação de renovação. |
| Visitante | Consulta autenticada do catálogo, conforme as regras de acesso da aplicação. |

## Funcionalidades Implementadas
### Autenticação e acesso
- Login por e-mail e senha.
- Senhas armazenadas com `password_hash` e verificadas com `password_verify`.
- Sessões PHP para manter autenticação.
- Proteção de páginas por login obrigatório e validação de perfil.
- Cadastro de novos alunos com turma e turno.
- Logout.

### Catálogo e livros
- Listagem e busca por título, autor, categoria, ISBN, código interno e código de barras.
- Cadastro, edição e exclusão de livros por administrador ou bibliotecário.
- Controle da quantidade disponível em estoque.
- Campos de título, autor, categoria, ISBN, código de barras, editora, ano, estante e código interno.
- Upload validado de capa de livro e PDF.
- Geração e consulta de código de barras.

### Empréstimos e devoluções
- Registro de empréstimo para usuário e livro selecionados.
- Validação de usuário, disponibilidade e limite máximo de empréstimos simultâneos.
- Baixa automática de uma unidade do estoque no empréstimo.
- Data de devolução calculada conforme `LOAN_DAYS`, atualmente 30 dias.
- Devolução com reposição automática do estoque.
- Bloqueio do usuário quando devolve um empréstimo após o vencimento.### Reservas
- Usuário autenticado pode registrar reserva de um livro.
- Reservas existentes ficam disponíveis somente para administração e bibliotecário.
- Alunos não acessam mais a área de reservas.
- Alunos podem solicitar renovação de empréstimos ativos, sujeita à aprovação administrativa.

### Painéis e relatórios
- Dashboard administrativo com indicadores do acervo e visão de leitura.
- Dashboard do aluno com catálogo, histórico de empréstimos, avisos de devolução próxima e atrasos.
- Atualização de foto de perfil, com avatares padrão.
- Relatório de empréstimos ativos, atrasados, livros com estoque baixo e usuários.

### Interface
- Tela de login e cadastro de aluno.
- Catálogo de livros, gestão de livros, empréstimos, devoluções, reservas e relatórios.
- Dashboard administrativo e dashboard do aluno.
- Tema claro/escuro e estilos responsivos globais.

## Requisitos Funcionais
| ID | Requisito | Situação |
|---|---|---|
| RF01 | Autenticar usuários por e-mail e senha. | Implementado |
| RF02 | Controlar acesso por perfil. | Implementado |
| RF03 | Cadastrar alunos. | Implementado |
| RF04 | Cadastrar, consultar, editar e excluir livros. | Implementado |
| RF05 | Controlar a quantidade disponível de cada livro. | Implementado |
| RF06 | Registrar empréstimos com data de vencimento. | Implementado |
| RF07 | Registrar devoluções e recompor estoque. | Implementado |
| RF08 | Bloquear novo empréstimo para usuário bloqueado. | Implementado |
| RF09 | Limitar empréstimos simultâneos por usuário. | Implementado |
| RF10 | Administrar reservas legadas. | Implementado parcialmente |
| RF11 | Exibir relatórios de empréstimos, atrasos e estoque. | Implementado |
| RF12 | Gerenciar usuários por administrador. | Pendente |

## Requisitos Não Funcionais
| ID | Requisito | Situação |
|---|---|---|
| RNF01 | Usar interface web responsiva. | Parcialmente implementado; requer validação em celular. |
| RNF02 | Proteger senhas com hash. | Implementado |
| RNF03 | Usar consultas parametrizadas no banco. | Implementado nos fluxos principais |
| RNF04 | Armazenar dados em MySQL na VM Linux. | Pendente; ambiente atual usa SQLite. |
| RNF05 | Executar em servidor Apache/PHP. | Implementado localmente no XAMPP; pendente em VM Linux. |
| RNF06 | Registrar testes manuais e automatizados. | Pendente |

## Modelagem de Dados
O banco possui as entidades abaixo.

```text
roles (1) --------< users
users (1) --------< loans >-------- (1) books
users (1) ---< reservations >--- (1) books
```

| Entidade | Campos principais | Relacionamentos |
|---|---|---|
| `roles` | id, name | Um perfil pode estar associado a vários usuários. |
| `users` | id, name, email, password, role_id, turma, turno, profile_photo, blocked, created_at | Um usuário possui um perfil, empréstimos e reservas. |
| `books` | id, title, author, category, isbn, barcode, publisher, year, quantity, shelf, internal_code, cover_path, pdf_path, created_at | Um livro pode integrar vários empréstimos e reservas. |
| `loans` | id, user_id, book_id, loaned_at, due_date, returned_at | Relaciona usuário e livro durante um empréstimo. |
| `reservations` | id, user_id, book_id, reserved_at, fulfilled_at | Relaciona usuário e livro durante uma reserva. |

## Casos de Uso
| Caso de uso | Ator | Fluxo resumido |
|---|---|---|
| Realizar login | Todos os usuários | Informa e-mail e senha; o sistema valida a senha e inicia a sessão. |
| Solicitar renovação | Aluno | Seleciona empréstimo ativo; o sistema registra a solicitação como pendente. |
| Aprovar renovação | Administrador/Bibliotecário | Analisa a solicitação e aprova ou recusa com justificativa. |
| Cadastrar aluno | Visitante/Aluno | Preenche nome, e-mail, senha, turma e turno; o sistema cria uma conta de aluno. |
| Gerenciar livros | Administrador/Bibliotecário | Cadastra, busca, edita ou exclui itens do acervo. |
| Registrar empréstimo | Administrador/Bibliotecário | Seleciona usuário e livro; o sistema valida bloqueio, estoque e limite antes de registrar. |
| Registrar devolução | Administrador/Bibliotecário | Localiza empréstimo aberto, registra devolução e incrementa o estoque. |
| Reservar livro | Usuário autenticado | Seleciona livro e cria uma reserva vinculada ao próprio usuário. |
| Cumprir reserva | Administrador/Bibliotecário | Confirma que há estoque e marca a reserva como cumprida. |
| Consultar relatórios | Administrador/Bibliotecário | Visualiza empréstimos ativos, atrasados, estoque baixo e usuários. |
| Consultar histórico | Aluno | Visualiza empréstimos atuais, devolvidos, próximos do vencimento e atrasados. |

## Estrutura do Projeto
```text
biblioteca/
|- api/                 # Recursos auxiliares, como código de barras
|- css/                 # Estilos compartilhados
|- data/                # Banco SQLite local gerado pelo setup
|- img/                 # Imagens e avatares padrão
|- includes/            # Conexão, autenticação, cabeçalho, rodapé e funções
|- uploads/             # Capas, PDFs e fotos de perfil enviados
|- books.php            # CRUD do acervo
|- loans.php            # Empréstimos e devoluções
|- reservations.php     # Reservas
|- reports.php          # Relatórios
|- dashboard.php        # Painel administrativo
|- student_dashboard.php# Painel do aluno
|- login.php            # Autenticação
|- register.php         # Cadastro de aluno
|- setup.php            # Criação e carga inicial do banco
`- config.php           # Configurações da aplicação
```

## Execução Local Atual
1. Instalar e iniciar Apache e PHP pelo XAMPP.
2. Colocar o projeto em `C:\xampp\htdocs\biblioteca`.
3. Conferir `BASE_URL` em `config.php`; no ambiente atual está definido como `/biblioteca/`.
4. Acessar `http://localhost/biblioteca/setup.php` uma vez para criar e popular o banco SQLite.
5. Acessar `http://localhost/biblioteca/login.php`.

Contas iniciais criadas pelo `setup.php`:

| Perfil | E-mail | Senha inicial |
|---|---|---|
| Administrador | admin@biblioteca.local | admin123 |
| Bibliotecário | bibliotecario@biblioteca.local | biblio123 |
| Aluno | aluno@biblioteca.local | aluno123 |
| Visitante | teste@biblioteca.local | teste123 |

As credenciais iniciais devem ser alteradas ou removidas antes de disponibilizar o sistema em rede.

## Critérios de Entrega e Situação Atual
### Pilar 1 — Gestão com Kanban
O código não contém o quadro de tarefas. O grupo deve manter um quadro no Trello, GitHub Projects ou Jira com as colunas `To Do`, `In Progress`, `Testing` e `Done`. Cada cartão deve conter descrição, responsável, prazo e critérios de aceite.

Sugestão de cartões já identificados:
- Documentar requisitos, DER e casos de uso.
- Migrar SQLite para MySQL.
- Configurar VM Linux, Apache, PHP e MySQL.
- Criar README e estratégia de branches.
- Implementar CRUD administrativo de usuários.
- Corrigir segurança de exclusões e ações de alteração.
- Criar testes automatizados.
- Executar testes de integração na VM.

### Pilar 2 — Documentação
Este arquivo registra visão geral, requisitos, entidades, relacionamentos e casos de uso. Ainda é necessário complementar com diagramas gráficos, regras de negócio detalhadas e evidências de revisão da equipe.

### Pilar 3 — Git e GitHub
A aplicação precisa ser publicada em repositório do grupo. Recomenda-se:
- `main`: versões estáveis para entrega.
- `develop`: integração do desenvolvimento.
- `feature/nome-da-tarefa`: implementação isolada de cada atividade.
- Pull requests com revisão antes do merge.
- Commits pequenos e descritivos.
- `README.md` com instalação, configuração, banco, contas de teste e deploy.

A existência do repositório remoto, das branches e do quadro Kanban deve ser comprovada pelos respectivos links na entrega.

### Pilar 4 — VM Linux e ambiente
Ainda pendente no código. A infraestrutura esperada é:
1. Criar VM VirtualBox, VMware ou provedor de nuvem.
2. Instalar Ubuntu Server ou Debian.
3. Configurar IP acessível na rede da escola.
4. Instalar Apache, PHP, extensões PDO MySQL e MySQL Server.
5. Configurar VirtualHost para o projeto.
6. Importar o banco MySQL e ajustar permissões de `uploads/` e `data/`.
7. Testar acesso pela rede local e registrar evidências, como IP, tela da VM e resposta HTTP.

### Pilar 5 — Banco e backend
Os fluxos PHP de livros, empréstimos, devoluções, reservas e relatórios estão implementados com PDO e consultas preparadas. O banco ainda precisa ser convertido de SQLite para MySQL e o CRUD completo de alunos/usuários precisa ser finalizado para atender integralmente ao critério.

### Pilar 6 — Frontend
Existem telas de login, cadastro, catálogo, livros, empréstimos, reservas, relatórios e dashboards. O CSS possui tema claro/escuro, cartões, tabelas e media queries. É necessário validar a tela de login e todos os formulários em celular, tablet e desktop.

### Pilar 7 — Testes
Ainda não existem testes automatizados nem plano de testes versionado. O mínimo recomendado é:

| ID | Teste | Resultado esperado | Tipo |
|---|---|---|---|
| T01 | Login com senha correta | Sessão criada e redirecionamento ao dashboard | Integração |
| T02 | Login com senha incorreta | Mensagem de erro e sessão não criada | Integração |
| T03 | Empréstimo de livro sem estoque | Operação recusada e estoque inalterado | Integração |
| T04 | Usuário no limite de empréstimos | Novo empréstimo recusado | Integração |
| T05 | Devolução de empréstimo em atraso | Estoque reposto e usuário bloqueado | Integração |
| T06 | Busca por título no catálogo | Somente livros correspondentes exibidos | Manual |
| T07 | Cadastro e edição de livro | Dados persistidos e exibidos na listagem | Manual |
| T08 | Reserva por aluno | Reserva vinculada ao usuário correto | Manual |
| T09 | Acesso de aluno à área administrativa | Resposta de acesso negado | Manual |
| T10 | Layout em tela pequena | Conteúdo sem rolagem horizontal indevida | Manual |

## Pendências Técnicas Prioritárias
1. Migrar a configuração e o schema de SQLite para MySQL.
2. Criar arquivo SQL versionado para instalação limpa.
3. Configurar e documentar a VM Linux com Apache, PHP e MySQL.
4. Criar `README.md` com instruções de execução local e na VM.
5. Implementar CRUD de usuários/alunos para administradores.
6. Trocar ações de alteração via `GET` por formulários `POST` com proteção CSRF.
7. Remover a senha fixa de exclusão em `books.php` e usar verificação da senha autenticada.
8. Fazer o cumprimento de reserva gerar também o empréstimo correspondente.
9. Evitar apagar histórico de empréstimos ao excluir livros; preferir arquivamento ou exclusão lógica.
10. Adicionar pelo menos três testes automatizados e publicar os resultados.
11. Criar e manter o Kanban com responsáveis e prazos.

## Cronograma de Entrega
### Fase 1 — Concepção e Infraestrutura
- Kanban configurado.
- Requisitos, DER e casos de uso documentados.
- Repositório GitHub criado.
- VM Linux criada e acessível.
- Apache, PHP e MySQL instalados e testados.

### Fase 2 — Desenvolvimento
- MySQL populado na VM.
- Rotas/fluxos backend integrados ao banco.
- CRUD de livros, alunos e empréstimos validado.
- Telas de login, catálogo, cadastro/edição e dashboard funcionando.

### Fase 3 — Integração e Testes
- Sistema completo executando na VM.
- Testes manuais registrados em tabela.
- Pelo menos três testes automatizados executados.
- Correções de segurança, responsividade e regras de negócio.
- Demonstração do acesso pela rede e entrega das evidências.

## Status Atual
A aplicação possui uma base funcional de biblioteca escolar em PHP, com autenticação, perfis, catálogo, CRUD de livros, empréstimos, devoluções, reservas, dashboards e relatórios. A documentação foi consolidada neste arquivo. Para atender integralmente aos critérios da atividade, permanecem obrigatórias a migração para MySQL, a implantação em VM Linux, a criação do README/Kanban, o CRUD administrativo de usuários e a implementação dos testes automatizados.
