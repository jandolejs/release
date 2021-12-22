<?php


namespace App\Model;

use App\Configuration;
use App\Model\Git\GitHub;
use App\Model\Task\Title;
use Exception;
use Nette;
use Nette\Database\Table\ActiveRow;

class Task
{
    use Nette\SmartObject;

    const FAIL_BACK_NAME = "-- Name could not be loaded --";

    const STATUS_NEW = 1;
    const STATUS_FAILED = 5;
    const STATUS_TESTING = 7;
    const STATUS_READY = 10;
    const STATUS_DEPLOYED = 13;

    const CHANGEABLE_STATUSES = [
        self::STATUS_FAILED,
        self::STATUS_TESTING,
        self::STATUS_READY,
    ];

    public static GitHub $gitHub;

    private int $id;
    private ActiveRow $row;


    /**
     * @throws Exception
     */
    public function __construct(ActiveRow $data)
    {
        $this->row = $data;
        $this->id  = $data->offsetGet('id');
    }

    public static function parsePullTitle($title): Title
    {
        return new Title($title);
    }

    /**
     * Find out Jira issue code 'PZ-2541' from PullRequest name
     * or something like 'Pz 2541' or 'PZ-2541 - Fix Something'
     * @param string $name PullRequest title or anything else
     * @return string Jira issue code like '2541'
     * @noinspection PhpUnused
     */
    public static function cleanPZ(string $name): string
    {
        $pz = self::parsePullTitle($name)->getCode();
        if (empty($pz)) return "";
        return Configuration::get("jira/prefix") . "-" . $pz;
    }

    /**
     * Clean PullRequest Title from PZ and PullRequest's #
     * @noinspection PhpUnused
     */
    public static function cleanName(string $input): string
    {
        // capitalize first letter
        $name = ucfirst(self::parsePullTitle($input)->getName());

        if (empty($name)) $name = self::FAIL_BACK_NAME;
        return $name;
    }

    public function addNote(string $note): self
    {
        $this->row->update(['note' => $note]);
        return $this;
    }

    public function delete()
    {
        $this->row->delete();
    }

    public function getPull(): string|null { return $this->getData('pull'); }

    public function getData(string $column): string|null
    {
        if ($column) {
            if ($this->getRow()->offsetExists($column))
                return $this->getRow()->offsetGet($column);
        }
        return NULL;
    }

    public function getRow(): ActiveRow { return $this->row; }

    public static function findStatusForPull(array $labels): int
    {
        $status = self::STATUS_NEW;

        if (in_array(Configuration::get("pull/label/ready"), $labels))
            $status = self::STATUS_READY;

        // If there is label that PR is ready for deploy, mark as ready
        foreach ($labels as $label) {
            if ($label->name == Configuration::get("pull/label/ready"))
                $status = self::STATUS_READY;
        }

        return $status;
    }

    public function getStatus(bool $string = FALSE): int|string
    {
        $status = $this->row->offsetGet('status');
        if ($string) $status = match ($status) {
            self::STATUS_NEW => 'New',
            self::STATUS_TESTING => "Testing",
            self::STATUS_FAILED => 'Failed',
            self::STATUS_READY => 'Ready',
            self::STATUS_DEPLOYED => 'Deployed',
            default => 'status_' . $status,
        };

        return $status;
    }

    /**
     * @param int $status
     * @param bool $check
     * @throws Exception
     */
    public function setStatus(int $status, bool $check = FALSE)
    {
        if ($check && !in_array($status, self::CHANGEABLE_STATUSES)) {
            throw new Exception("Status cannot be changed like this");
        }

        $this->row->update(['status' => $status]);
    }

    public function __get(string $name)
    {
        return $this->row->offsetGet($name);
    }

    /** @noinspection PhpUnused */
    public function getNote(): string|null { return $this->getData('note'); }

    public function getBranch(): string|null { return $this->getData('branch'); }

    public function getSha(): string|null { return $this->getData('sha'); }

    public function getReleaseNo(): int {return $this->getData('release'); }

    public function getId(): int {return $this->id; }

    /**
     * @return string
     * @noinspection PhpUnused
     */
    public function getPullLink(): string
    {
        return self::$gitHub->getPullLink($this->row->offsetGet('pull'));
    }

    public function isCleanToMerge(): bool
    {
        if (!$this->isMergeable()) return false;
        if (in_array($this->getMergeableStatus(), ['unstable', 'clean'])) return true;
        return false;
    }

    /**
     * @return string
     * @noinspection PhpUnused
     */
    public function getIssueLink(): string
    {
        return Configuration::get('jira/issue_url') . "/" . $this->getPZ(true);
    }

    public function getCreator(): string|null { return $this->getData('creator'); }

    public function getPZ(bool $full = FALSE): string
    {
        $pz = $this->row->offsetGet('pz');
        if ($full) $pz = Configuration::get('jira/prefix') . "-" . $pz;
        return $pz;
    }

    public function getName(): string|null {
        return $this->getData('name');
    }

    /**
     * Check if task has manual action label
     */
    public function hasManualAction(): bool
    {
        foreach ($this->getLabels(true) as $label) {
            if (Configuration::get("pull/label/manual") === $label) return true;
        }
        return false;
    }

    /**
     * @noinspection PhpUnused
     */
    public function hasApprove(): bool
    {
        return (bool) $this->getRow()->offsetGet('approve');
    }

    public function getLabels($short = false): array
    {
        $labels = json_decode($this->row->offsetGet('labels'), TRUE);
        if (empty($labels[0])) $labels = array();

        // Add Not fetched label if Pull is not fully loaded from GitHub
        if (!$this->row->offsetGet('fetched'))
            array_unshift($labels, ['name' => 'Not fetched', 'color' => '67510d', 'description' => 'This task was not merged into this release']);

        // If only names should be returned
        if ($short) {
            $original = $labels;
            $labels = array();
            foreach ($original as $label) {
                $labels[] = $label['name'];
            }
        }

        return $labels;
    }

    /**
     * Set tasks status to approved and add label to GitHub
     * @throws Exception
     */
    public function approve()
    {
        $this->setStatus(self::STATUS_READY, TRUE);
        self::$gitHub->setReady($this->row->offsetGet('pull'));
    }

    /**
     * Set tasks status to failed and remove label from GitHub
     * @throws Exception
     */
    public function failed()
    {
        self::$gitHub->removeReady($this->getPull());
        $this->setStatus(self::STATUS_FAILED, TRUE);
    }

    public function getMergeableStatus(): string
    {
        return $this->getRow()->offsetGet('mergeable_state') ?? 'unknown';
    }

    public function isMergeable(): bool
    {
        return (bool) $this->getRow()->offsetGet('mergeable');
    }


    public function getPullRow(): string
    {
        return "\n\t<tr>"
            ."\n\t\t<td><a href='" . addslashes("https://github.com/{$this->getCreator()}")."'>{$this->getCreator()}</a></td>"
            ."\n\t\t<td><a href='" . addslashes(Configuration::get("jira/url") . "/browse/{$this->getPZ(TRUE)}") . "'>{$this->getPZ(TRUE)}</a></td>"
            ."\n\t\t<td>" . htmlspecialchars($this->getName()) . "</td>"
            ."\n\t\t<td>#" . htmlspecialchars($this->getPull()) . "</td>"
            ."\n\t\t<td>" . htmlspecialchars($this->hasManualAction() ? "Manual Action" : "") . "</td>"
            //."\n\t\t<td>" . htmlspecialchars($this->getPull()) . "</td>"
        ."\n\t</tr>";
    }
}
