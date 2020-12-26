<?php

declare(strict_types=1);

namespace App\Conversations;

use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;

use BotMan\BotMan\Storages\Storage;
use function Otis22\VetmanagerUrl\url;
use function Otis22\VetmanagerToken\token;
use function Otis22\VetmanagerToken\credentials;
use function config;

final class AuthConversation extends Conversation
{
    /**
     * @var string
     */
    protected $clinicUrl;
    /**
     * @var string
     */
    protected $userLogin;
    /*
     * @var string
     */
    protected $token;
    /**
     * @return Conversation
     */
    public function askDomain(): Conversation
    {
        return $this->ask("Введите доменное имя или адрес программы. Пример: myclinic или https://myclinic.vetmanager.ru", function (Answer $answer) {
            try {
                $this->clinicUrl = url($answer->getValue())->asString();
                $this->askLogin();
            } catch (\Throwable $exception) {
                $this->say("Попробуйте еще раз. Ошибка: " . $exception->getMessage());
                $this->askDomain();
            }
        });
    }

    /**
     * @return Conversation
     */
    public function askLogin(): Conversation
    {
        return $this->ask("Введите login вашего пользователя в Ветменеджер", function (Answer $answer) {
            $this->userLogin = $answer->getValue();
            $this->askPassword();
        });
    }

    public function askPassword(): Conversation
    {
        return $this->ask("Введите пароль вашего пользователя в Ветменеджер", function (Answer $answer) {
            $password = $answer->getValue();
            $credentials = credentials(
                $this->userLogin,
                $password,
                config('app.name')
            );
            try {
                $token = token($credentials, $this->clinicUrl)->asString();
                $this->getBot()->userStorage()
                    ->save(
                        ['clinicUserToken' => $token]
                    );
                $this->say('Success ' .$token);
                $this->say('From storage' . $this->userData()->get('clinicUserToken'));
            } catch (\Throwable $exception) {
                $this->say("Попробуйте еще раз. Ошибка: " . $exception->getMessage());
                $this->askDomain();
            }
        });
    }

    public function run()
    {
        if (empty($this->userData()->get('clinicUrl'))) {
            $this->say("Привет, Босс, ответьте на 3 вопроса");
            $this->askDomain();
            return;
        }
        if (empty($this->userData()->get('clinicUserLogin'))) {
            $this->say("Привет, Босс, ответьте на 2 вопроса");
            $this->askLogin();
            return;
        }
    }
    private function userData(): Storage
    {
        return $this->getBot()
            ->userStorage(
                $this->getBot()->getUser()
            );
    }
}
