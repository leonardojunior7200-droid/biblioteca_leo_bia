<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

try {
    $db = get_db();
    ensure_user_profile_photo_column();
    ensure_book_pdf_column();

    $db->exec('CREATE TABLE IF NOT EXISTS roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        role_id INTEGER NOT NULL,
        blocked INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(role_id) REFERENCES roles(id)
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS books (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        author TEXT NOT NULL,
        category TEXT NOT NULL,
        isbn TEXT,
        publisher TEXT,
        year INTEGER,
        quantity INTEGER NOT NULL DEFAULT 0,
        shelf TEXT,
        internal_code TEXT,
        cover_path TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS loans (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        book_id INTEGER NOT NULL,
        loaned_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        due_date TEXT NOT NULL,
        returned_at TEXT,
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(book_id) REFERENCES books(id)
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS reservations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        book_id INTEGER NOT NULL,
        reserved_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fulfilled_at TEXT,
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(book_id) REFERENCES books(id)
    )');

    $roles = ['Administrador', 'Bibliotecário', 'Aluno', 'Visitante'];
    $stmt = $db->prepare('INSERT OR IGNORE INTO roles (name) VALUES (:name)');
    foreach ($roles as $role) {
        $stmt->execute([':name' => $role]);
    }

    $roleStmt = $db->query('SELECT id, name FROM roles');
    $rolesMap = [];
    foreach ($roleStmt->fetchAll() as $row) {
        $rolesMap[$row['name']] = $row['id'];
    }

    $users = [
        ['name' => 'Admin Escolar', 'email' => 'admin@biblioteca.local', 'password' => password_hash('admin123', PASSWORD_DEFAULT), 'role' => 'Administrador'],
        ['name' => 'Bibliotecário', 'email' => 'bibliotecario@biblioteca.local', 'password' => password_hash('biblio123', PASSWORD_DEFAULT), 'role' => 'Bibliotecário'],
        ['name' => 'Aluno Exemplo', 'email' => 'aluno@biblioteca.local', 'password' => password_hash('aluno123', PASSWORD_DEFAULT), 'role' => 'Aluno'],
        ['name' => 'Usuário de Teste', 'email' => 'teste@biblioteca.local', 'password' => password_hash('teste123', PASSWORD_DEFAULT), 'role' => 'Visitante'],
    ];

    $stmt = $db->prepare('INSERT OR IGNORE INTO users (name, email, password, role_id, blocked) VALUES (:name, :email, :password, :role_id, 0)');
    foreach ($users as $user) {
        $stmt->execute([
            ':name' => $user['name'],
            ':email' => $user['email'],
            ':password' => $user['password'],
            ':role_id' => $rolesMap[$user['role']],
        ]);
    }

    $db->exec('INSERT OR IGNORE INTO books (title, author, category, isbn, publisher, year, quantity, shelf, internal_code) VALUES
        ("O Pequeno Príncipe", "Antoine de Saint-Exupéry", "Literatura", "9788520014702", "Editora A", 1943, 5, "A1", "BP-001"),
        ("Dom Casmurro", "Machado de Assis", "Literatura", "9788503010334", "Editora B", 1899, 3, "A2", "BP-002"),
        ("Matemática Básica", "José da Silva", "Didático", "9788538000011", "Editora C", 2015, 4, "B1", "BP-003")');

    $message = 'Banco de dados inicializado com sucesso. Use o email admin@biblioteca.local e senha admin123 para entrar.';
} catch (Exception $e) {
    $message = 'Falha ao inicializar o banco de dados: ' . $e->getMessage();
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Configuração concluída</h1>
    <p><?php echo h($message); ?></p>
    <p><a href="<?php echo h(base_url('login.php')); ?>">Ir para o login</a></p>
    <p><a href="<?php echo h(base_url('index.php')); ?>">Voltar ao catálogo</a></p>
</div>
<?php require_once __DIR__ . '/includes/footer.php';
