<?php


namespace App\Model\Git;


use App\Configuration;
use App\Model\Task;
use stdClass;

class PullRequest
{
    private stdClass $data;
    private GitHub $gitHub;
    private int $id;

    public function __construct(GitHub $gitHub, int $id) {
        $this->id = $id;
        $this->gitHub = $gitHub;
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @throws Exception
     */
    public function fetch(): stdClass
    {
        $this->data = $this->gitHub->call("pulls/{$this->getId()}");
        return $this->data;
    }

    public function getLabels($short = false): array
    {
        $labels = $this->data->labels;

        // If only names should be returned
        if ($short) {
            $original = $labels;
            $labels = array();
            foreach ($original as $label) {
                $labels[] = $label->name;
            }
        }

        return $labels;
    }

    public function getArray(int $releaseId): array
    {
        return [
            'pull'      => $this->data->number,
            'release'   => $releaseId,
            'pz'        => Task::parsePullTitle($this->data->title)->getCode(),
            'name'      => Task::parsePullTitle($this->data->title)->getName(),
            'creator'   => $this->data->user->login,
            'branch'    => $this->data->head->ref,
            'sha'       => $this->data->head->sha,
            'manual'    => $this->hasManual(),
            'labels'    => json_encode($this->getLabels()),
            'status'    => Task::findStatusForPull($this->getLabels()),
            'fetched'   => TRUE,
            'mergeable_state' => $this->data->mergeable_state,
            'mergeable' => $this->data->mergeable ?? false,
            //'object' => serialize($this->data),
        ];
    }

    private function hasManual(): bool
    {
        $manual = false;

        foreach ($this->getLabels() as $label) {
            if (Configuration::get("pull/label/manual") === $label) $manual = true;
        }

        return $manual;
    }
}
