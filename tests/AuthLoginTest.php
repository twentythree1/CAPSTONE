<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../CAFCA-MS/login/Auth.php';

class FakeStmt {
    public $num_rows = 0;
    private $row;
    private $idRef;
    private $hashRef;

    public function __construct(int $numRows, ?array $row = null) {
        $this->num_rows = $numRows;
        $this->row = $row;
    }

    public function bind_param($types, &...$params) {}
    public function execute() { return true; }
    public function store_result() {}

    public function bind_result(&...$refs) {
        $this->idRef = &$refs[0];
        $this->hashRef = &$refs[1];
    }

    public function fetch() {
        if ($this->row) {
            $this->idRef = $this->row['id'];
            $this->hashRef = $this->row['password_hash'];
        }
    }

    public function close() {}
}

class FakeConn {
    private $stmts;
    public function __construct(array $stmts) { $this->stmts = $stmts; }
    public function prepare($sql) { return array_shift($this->stmts); }
}

final class AuthLoginTest extends TestCase
{
    public function testLoginReturnsUserIdWhenPasswordIsCorrect(): void
    {
        $hash = password_hash('secret123', PASSWORD_DEFAULT);
        $stmt = new FakeStmt(1, ['id' => 7, 'password_hash' => $hash]);
        $auth = new Auth(new FakeConn([$stmt]));

        $result = $auth->login('john', 'secret123');

        $this->assertSame(7, $result);
    }

    public function testLoginReturnsUserNotFoundWhenNoUserExists(): void
    {
        $stmt = new FakeStmt(0);
        $auth = new Auth(new FakeConn([$stmt]));

        $result = $auth->login('unknown', 'secret123');

        $this->assertSame('User not found.', $result);
    }

    public function testLoginReturnsIncorrectPasswordWhenPasswordIsWrong(): void
    {
        $hash = password_hash('secret123', PASSWORD_DEFAULT);
        $stmt = new FakeStmt(1, ['id' => 7, 'password_hash' => $hash]);
        $auth = new Auth(new FakeConn([$stmt]));

        $result = $auth->login('john', 'wrong-pass');

        $this->assertSame('Incorrect password.', $result);
    }
}
