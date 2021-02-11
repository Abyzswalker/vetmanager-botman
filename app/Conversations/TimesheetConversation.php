<?php

declare(strict_types=1);

namespace App\Conversations;

use App\Vetmanager\UserData\ClinicToken;
use App\Vetmanager\UserData\UserRepository\UserRepository;
use BotMan\BotMan\Messages\Conversations\Conversation;
use App\Http\Helpers\Rest\Users;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use GuzzleHttp\Client;
use App\Http\Helpers\Rest\Schedules;
use Otis22\VetmanagerToken\Token\Concrete;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use App\Vetmanager\UserData\ClinicUrl;
use function Otis22\VetmanagerUrl\url;

final class TimesheetConversation extends Conversation
{

    public function saySchedule(): void
    {
        $user = UserRepository::getById($this->getBot()->getUser()->getId());
        $token = new Concrete(
            (
            new ClinicToken(
                $user
            )
            )->asString()
        );
        $baseUri = (
            new ClinicUrl(
                function (string $domain) : string {
                    return url($domain)->asString();
                },
                $user
            )
        )->asString();
        $client = new Client(
            [
                'base_uri' => $baseUri,
                'headers' => ['X-USER-TOKEN' => $token->asString(), 'X-APP-NAME' => config('app.name')]
            ]
        );
        $schedules = new Schedules($client);
        $daysCount = $this->bot->userStorage()->get('daysCount');
        $doctorId = $this->bot->userStorage()->get('doctorId');
        $clinicId = $this->bot->userStorage()->get('clinicId');
        $timesheets = $schedules->byIntervalInDays($daysCount, $doctorId, $clinicId)['data']['timesheet'];
        if (empty($timesheets)) {
            $this->say("Нет данных");
        }
        foreach ($timesheets as $timesheet) {
            $date = date_format(date_create($timesheet['begin_datetime']), "d.m.Y");
            $from = date_format(date_create($timesheet['begin_datetime']), "H:i:s");
            $to = date_format(date_create($timesheet['end_datetime']), "H:i:s");
            $this->say("{$date} $from - $to");
        }
    }

    private function askDaysCount()
    {
        $this->ask("Введите количество дней", function (Answer $answer) {
            $daysCount = $answer->getText();
            try {
                if (!is_numeric($daysCount) or $daysCount == 0) {
                    throw new \Exception("Ошибка. Проверьте введенные данные! {$daysCount} - неправильные данные" );
                }
                $this->bot->userStorage()->save(compact('daysCount'));
                return $this->askClinicId();
            } catch (\Exception $e) {
                $this->say($e->getMessage());
                return $this->askDaysCount();
            }
        });
    }

    private function askClinicId()
    {
        $this->ask("Введите ID клиники(0, чтобы отобразить по всем клиникам)", function (Answer $answer) {
            $clinicId = $answer->getText();
            try {
                if (!is_numeric($clinicId)) {
                    throw new \Exception("Ошибка. Проверьте введенные данные!");
                }
                $this->bot->userStorage()->save(compact('clinicId'));
                return $this->askDoctorId();
            } catch (\Exception $e) {
                $this->say($e->getMessage());
            }
        });
    }

    private function askDoctorId() {
        $user = UserRepository::getById($this->getBot()->getUser()->getId());
        $token = new Concrete(
            (
            new ClinicToken(
                $user
            )
            )->asString()
        );
        $baseUri = (
            new ClinicUrl(
                function (string $domain) : string {
                    return url($domain)->asString();
                },
                $user
            )
        )->asString();
        $client = new Client(
            [
                'base_uri' => $baseUri,
                'headers' => ['X-USER-TOKEN' => $token->asString(), 'X-APP-NAME' => config('app.name')]
            ]
        );
        $users = new Users($client);
        foreach ($users->all()['data']['user'] as $user) {
            $text = $user['first_name'] . " " . $user['last_name'] . " " . $user['login'];
            $buttons[] = Button::create($text)->value($user['id']);
        }

        $question = Question::create('Выберите врача')
            ->callbackId('select_doctor')
            ->addButtons($buttons);

        $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                $this->say("Результаты для ID " . $answer->getText());
                $doctorId = $answer->getValue();
                try {
                    if (!is_numeric($doctorId)) {
                        throw new \Exception("Ошибка. Проверьте введенные данные!");
                    }
                    $this->bot->userStorage()->save(compact('doctorId'));
                    return $this->saySchedule();
                } catch (\Exception $e) {
                    $this->say($e->getMessage());
                    return $this->askDoctorId();
                }
            }
        });

    }
    /**
     * @param IncomingMessage $message
     * @return bool
     */
    public function stopsConversation(IncomingMessage $message): bool
    {
        if ($message->getText() == 'stop') {
            return true;
        }

        return false;
    }
    public function run()
    {
        $this->askDaysCount();
    }
}
