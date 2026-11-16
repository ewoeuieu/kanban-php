<?php

declare(strict_types=1);

namespace App;

use App\Http\HttpError;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

final class Auth
{
    public function __construct(
        private readonly DatabaseSessionHandler $handler,
        private readonly array $settings,
    ) {}

    public function start(): void
    {
        session_name('KANBANSESSID');
        session_set_cookie_params([
            'path' => '/',
            'secure' => $this->settings['secure'],
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_set_save_handler($this->handler, true);
        if (!session_start([
            'use_strict_mode' => 1,
            'use_only_cookies' => 1,
            'use_trans_sid' => 0,
            'gc_probability' => 0,
            'gc_maxlifetime' => $this->settings['ttl'],
        ])) {
            throw new RuntimeException('Не удалось открыть сессию.');
        }
    }

    public function user(): ?User
    {
        $id = $_SESSION['user_id'] ?? null;

        return is_int($id) ? User::query()->find($id) : null;
    }

    public function register(string $email, string $name, string $password): User
    {
        try {
            $user = User::query()->create([
                'email' => strtolower($email),
                'name' => $name,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new HttpError(409, 'Этот email уже зарегистрирован.');
        }

        $this->setUser($user);

        return $user;
    }

    public function login(string $email, string $password): User
    {
        $user = User::query()->where('email', strtolower($email))->first();
        if ($user === null || !password_verify($password, $user->password_hash)) {
            throw new HttpError(401, 'Неверный email или пароль.');
        }

        if (password_needs_rehash($user->password_hash, PASSWORD_DEFAULT)) {
            $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
            $user->save();
        }
        $this->setUser($user);

        return $user;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (!session_destroy()) {
            throw new RuntimeException('Не удалось удалить сессию.');
        }
        setcookie(session_name(), '', [
            'expires' => 1,
            'path' => '/',
            'secure' => $this->settings['secure'],
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    private function setUser(User $user): void
    {
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Не удалось обновить идентификатор сессии.');
        }
        $_SESSION = ['user_id' => $user->id];
    }
}
