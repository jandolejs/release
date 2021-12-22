<?php


namespace App\Model;


use App\Configuration;
use JetBrains\PhpStorm\ArrayShape;
use Nette\Database\Explorer;
use Nette\Database\Table\Selection;
use Nette\Security\User;

class Statistics
{

    private Explorer $database;
    private int|null $userId;

    public function __construct(Explorer $database, User $user)
    {
        $this->database = $database;
        $this->userId   = $user->getId();
    }

    public function create(string $type, string $action, $value)
    {
        $this->getTable()->insert([
            'type'    => $type,
            'action'  => $action,
            'value'   => (string)$value,
            'user_id' => $this->userId,
        ]);
    }

    public function visit(string $url, string $type = 'default')
    {
        $this->create('url', $type, $url);
    }

    #[ArrayShape(['all' => "array", 'release' => "array[]", 'task' => "array[]"])]
    public function getAllStatisticsTogether(): array
    {
        // fetch results
        $result =  [
            'all' => [
                'this' => $this->getStatistic(null, null, true, 0),
                'last' => $this->getStatistic(null, null, true, -1),
            ],
            'release' => [
                'create' => [
                    'this' => $this->getStatistic('release', 'create', true, 0),
                    'last' => $this->getStatistic('release', 'create', true, -1),
                    'today' => $this->getStatistic('release', 'create', true, null, 0),
                    'yesterday' => $this->getStatistic('release', 'create', true, null, -1),
                ],
            ],
            'task' => [
                'failed' => [
                    'this' => $this->getStatistic('task', 'failed', true, 0),
                    'last' => $this->getStatistic('task', 'failed', true, -1),
                    'today' => $this->getStatistic('task', 'failed', true, null, 0),
                    'yesterday' => $this->getStatistic('task', 'failed', true, null, -1),
                ],
                'import' => [
                    'this' => $this->getStatistic('task', 'import', true, 0),
                    'last' => $this->getStatistic('task', 'import', true, -1),
                    'today' => $this->getStatistic('task', 'import', true, null, 0),
                    'yesterday' => $this->getStatistic('task', 'import', true, null, -1),
                ],
            ],
        ];

        // additional sums
        $result['task']['sum']['today'] = array_merge($result['task']['import']['today'], $result['task']['failed']['today']);
        $result['task']['sum']['yesterday'] = array_merge($result['task']['import']['yesterday'], $result['task']['failed']['yesterday']);
        $result['task']['sum']['this'] = array_merge($result['task']['import']['this'], $result['task']['failed']['this']);
        $result['task']['sum']['last'] = array_merge($result['task']['import']['last'], $result['task']['failed']['last']);

        // return results
        return $result;
    }

    private function getTable(): Selection
    {
        return clone $this->database->table(Configuration::get('statistics/table'));
    }

    private function getStatistic(string $type = null, string $action = null, bool $group = false, ?int $month = null, ?int $day = null): array
    {
        $where = array();

        if (isset($type)) $where[] = ['type' => $type];
        if (isset($type)) $where[] = ['action' => $action];

        if ($month !== null) {
            $where[] = [
                'date LIKE' => date('Y-m', strtotime("first day of $month month")) . '%',
            ];
        }
        if ($day !== null) {
            $where[] = [
                'date LIKE' => date('Y-m-d', strtotime("today $day day")) . "%",
            ];
        }

        $fetched = $this->getTable()->order('date DESC')->where($where);

        if ($group) $fetched = $fetched->group("value");

        return $fetched->fetchAll() ?? [];
    }
}
